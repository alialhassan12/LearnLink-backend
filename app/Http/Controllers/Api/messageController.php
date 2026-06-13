<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Services\SupabaseStorageService;
use App\Services\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class messageController extends Controller
{
    public function send(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'content' => 'nullable|string',
            'type' => 'in:text,file,image',
            'file' => 'nullable|file|mimes:pdf,doc,docx,txt,jpg,jpeg,png,gif,mp4,mov',
            'file_name' => 'nullable|string'
        ]);

        if (empty($request->content) && empty($request->file)) {
            return response()->json([
                'message' => 'Message content or file is required',
            ], 422);
        }

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }
        $sender_id = $user->id;
        $receiver_id = $request->receiver_id;

        $conversation = ConversationService::findOrCreateDirectConversation($sender_id, $receiver_id);

        $message = DB::transaction(function () use ($sender_id, $receiver_id, $storage, $request, $conversation) {
            $file_url = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $file_url = $storage->uploadMessageFile($file, $sender_id, $receiver_id);
            }

            $message = Message::create([
                "sender_id" => $sender_id,
                "conversation_id" => $conversation->id,
                "content" => $request->content ?? null,
                "type" => $request->type ?? 'text',
                "file_name" => $request->file_name ?? null,
                "file_url" => $file_url,
            ]);

            $conversation->update([
                'last_message_id' => $message->id,
                'updated_at' => now(),
            ]);

            if ($message->type == "image" || $message->type == "file") {
                $message->file_url = $storage->getPublicUrl($message->file_url);
            }

            return $message;
        });


        broadcast(new MessageSent($message->load('sender')));

        return response()->json([
            'message' => $message,
        ], 201);
    }

    public function getMessagesByConversation(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
        ]);
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }
        $messages = Message::where('conversation_id', $request->conversation_id)->orderBy('created_at', 'asc')->with('sender')->get();

        foreach ($messages as $message) {
            if ($message->file_url) {
                $message->file_url = $storage->getPublicUrl($message->file_url);
            }
            if ($message->sender->avatar) {
                $message->sender->avatar = $storage->getPublicUrl($message->sender->avatar);
            }
        }

        return response()->json([
            'messages' => $messages,
        ], 200);
    }
}
