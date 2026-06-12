<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiChat;
use App\Models\AiMessage;
use App\Models\Subscription;
use App\Services\AiService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Event\Code\Throwable;

class aiMessagesController extends Controller
{
    public function sendMessage(Request $request, AiService $aiService){
        $request->validate([
            "prompt"=>"required|string",
            "ai_chat_id"=>"nullable|exists:ai_chats,id",
            "chat_title"=>"nullable|string",
        ]);
        try{
            $user=auth('sanctum')->user();
            if(!$user){
                return response()->json([
                    "message"=>"Unauthorized Access"
                ],401);
            }

            // check subscription plan
            $subscription=Subscription::with('plan')->where('user_id',$user->id)->first();
            if(!$subscription || $subscription->status !=='active'){
                return response()->json([
                    "message"=>"You are not subscribed to any plan"
                ],400);
            }
            // check plan limit
            if($subscription->tokens_used >= $subscription->plan->features['ai_tokens_per_month']){
                return response()->json([
                    "message"=>"You have exceeded your monthly AI token limit"
                ],400);
            }

            $chat=null;
            
            if(!$request->filled('ai_chat_id')){
                $chat=AiChat::create([
                    "user_id"=>$user->id,
                    "title"=>$request->chat_title ?? "New Chat",
                ]);
            }else{
                $chat=AiChat::where('id',$request->ai_chat_id)->where('user_id',$user->id)->firstOrFail();
            }

            // call ai service
            $aiResponse=$aiService->generate($request->prompt);

            if($aiResponse==null){
                return response()->json([
                    "message"=>"Failed to connect to AI server"
                ],500);
            }

            // extract data from ai response 
            // for google ai
            $responseParts=$aiResponse['candidates'][0]['content']['parts'];
            $aiText=collect($responseParts)
                    ->filter(fn ($part)=>!($part['thought']??false))
                    ->pluck('text')
                    ->implode('\n');


            $aiTokenUsage=$aiResponse['usageMetadata']['totalTokenCount'];

            // for ollama model
            // $aiText=$aiResponse['response'];
            // $aiTokenUsage=$aiResponse['prompt_eval_count']+$aiResponse['eval_count'];

            $aiMessage=DB::transaction(function()use($chat,$request,$aiText,$aiTokenUsage,$subscription){
                // update token usage
                $subscription->tokens_used+=$aiTokenUsage;
                $subscription->save();
                
                // save user and ai messages
                AiMessage::create([
                    "ai_chat_id"=>$chat->id,
                    "role"=>"user",
                    "content"=>$request->prompt,
                    "type"=>"text",
                    "tokens_used"=>0
                ]);
        
                $aiMessage=AiMessage::create([
                    "ai_chat_id"=>$chat->id,
                    "role"=>"assistant",
                    "content"=>$aiText,
                    "type"=>"text",
                    "tokens_used"=>$aiTokenUsage
                ]);
                return $aiMessage;
            });

            return response()->json([
                "message"=>"Message sent successfully",
                "chat"=>$chat,
                "ai_message"=>$aiMessage
            ],200);
        }catch(\Throwable $e){
            Log::error('AI message failed', ['error' => $e]);
            return response()->json([
                'message' => 'An error occurred while processing your request',
            ], 500);
        }
    }

    public function sendMessageWithFile(Request $request,AiService $aiService,SupabaseStorageService $storage){
        $request->validate([
            "prompt"=>"required|string",
            "ai_chat_id"=>"nullable|exists:ai_chats,id",
            "chat_title"=>"nullable|string",
            "file"=>"required|file|mimes:pdf,doc,docx,txt,jpg,jpeg,png,webm|max:50000"
        ]);

        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated"
            ],401);
        }

        try{
            // check subscription plan
            $subscription=Subscription::with('plan')->where('user_id',$user->id)->first();
            if(!$subscription || $subscription->status !=='active'){
                return response()->json([
                    "message"=>"You are not subscribed to any plan"
                ],400);
            }
            // check plan limit
            if($subscription->tokens_used >= $subscription->plan->features['ai_tokens_per_month']){
                return response()->json([
                    "message"=>"You have exceeded your monthly AI token limit"
                ],400);
            }

            $chat=null;
            if($request->filled('ai_chat_id')){
                $chat=AiChat::where('id',$request->ai_chat_id)->where('user_id',$user->id)->firstOrFail();
            }else{
                $chat=AiChat::create([
                    "user_id"=>$user->id,
                    "title"=>$request->chat_title ?? "New Chat",
                ]);
            }

            $aiResponse=$aiService->generateWithFile($request->prompt,$request->file);

            if($aiResponse==null){
                return response()->json([
                    "message"=>"Failed to connect to AI server"
                ],500);
            }

            // extract data from ai response
            $responseParts=$aiResponse['candidates'][0]['content']['parts'];
            $aiText=collect($responseParts)
                    ->filter(fn ($part)=>!($part['thought']??false))
                    ->pluck('text')
                    ->implode('\n');


            $aiTokenUsage=$aiResponse['usageMetadata']['totalTokenCount'];

            $aiMessage=DB::transaction(function() use ($user,$chat,$request,$aiText,$aiTokenUsage,$subscription,$storage){
                // update token usage
                $subscription->tokens_used+=$aiTokenUsage;
                $subscription->save();


                // upload file to supabase
                $fileTitle=date('Y-m-d_H-i-s');
                $fileType=$request->file->getClientMimeType();
                $file_path=$storage->uploadAiChatDocuments($request->file,$user->id,$fileTitle);
                
                // save user and ai messages
                AiMessage::create([
                    "ai_chat_id"=>$chat->id,
                    "role"=>"user",
                    "content"=>$request->prompt,
                    "type"=>"file",
                    "file_name"=>$fileTitle,
                    "file_path"=>$file_path,
                    "file_type"=>$fileType,
                    "tokens_used"=>0
                ]);
        
                $aiMessage=AiMessage::create([
                    "ai_chat_id"=>$chat->id,
                    "role"=>"assistant",
                    "content"=>$aiText,
                    "type"=>"text",
                    "tokens_used"=>$aiTokenUsage
                ]);
                return $aiMessage;
            });

            return response()->json([
                "message"=>"Message sent successfully",
                "chat"=>$chat,
                "ai_message"=>$aiMessage
            ],200);
        
        }catch(\Throwable $e){
            return response()->json([
                "message"=>$e->getMessage(),
                "line"=>$e->getLine(),
                "file"=>$e->getFile()
            ],500);
        }
    }

    public function getMessages(Request $request,SupabaseStorageService $storage){
        $request->validate([
            "ai_chat_id"=>'required|exists:ai_chats,id'
        ]);

        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated"
            ],401);
        }
        $chat=AiChat::with('aiMessages')
                    ->where('id',$request->ai_chat_id)
                    ->where('user_id',$user->id)
                    ->firstOrFail();
        
        $messages=$chat->aiMessages;

        if($messages->isEmpty()){
            return response()->json([
                "message"=>"No messages found"
            ],404);
        }

        foreach($messages as $message){
            if($message->type=="file"){
                $message->file_path=$storage->getPublicUrl($message->file_path);
            }
        }

        return response()->json([
            "message"=>"Messages retrieved successfully",
            "messages"=>$messages
        ],200);
    }
}
