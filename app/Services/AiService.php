<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class AiService
{
    private string $googleAiApiKey;
    private string $googleAiUrl;
    private string $ollamaModel;
    private string $ollamaUrl="http://127.0.0.1:11434/api/generate";
    private DocumentExtractorService $documentExtractor;

    public function __construct(DocumentExtractorService $documentExtractor)
    {
        $this->documentExtractor=$documentExtractor;
        $this->googleAiApiKey = config("services.google_ai.api_key");
        $this->googleAiUrl=config('services.google_ai.url').config('services.google_ai.model').':generateContent?key=';
        $this->ollamaModel=config("services.ollama.model");
    }

    public function generate(string $prompt,User $user){

        $systemPrompt=config("ai.system_prompt");
        $systemPrompt .= "\n\nCurrent User Information:";
        $systemPrompt .= "\n\nRole: $user->role";

        // Google Ai response
        $response=Http::connectTimeout(120)
                ->timeout(120)
                ->post($this->googleAiUrl.$this->googleAiApiKey,[
                    "systemInstruction"=>[
                        "parts"=>[
                            [
                                "text"=>$systemPrompt
                            ]
                        ]
                    ],
                    "contents"=>[
                        [
                            "role"=>"user",
                            "parts"=>[
                                [
                                    "text"=>$prompt
                                ]
                            ]
                        ]
                    ]
                ]);


        // ollama response
        // $response=Http::connectTimeout(120)
        //         ->timeout(120)
        //         ->post($this->ollamaUrl,[
        //             'model'=>$this->ollamaModel,
        //             'prompt'=>$prompt,
        //             'stream'=>false
        //         ]);

        // check if response status is ok
        if(!$response->successful()){
            return null;
        }

        return $response->json();
    }

    public function generateWithFile(string $prompt,UploadedFile $file,User $user){
        $systemPrompt=config("ai.system_prompt");
        $systemPrompt .= "\n\nCurrent User Information:";
        $systemPrompt .= "\n\nRole: $user->role";

        $fileType=$file->getMimeType();

        if(str_starts_with($fileType,'image/')){
            $promptForImages="
                The user uploaded an image.

                Instructions:
                - Base your answer only on what is visible in the image.
                - Do not assume information that is not present.
                - If something is unclear, say so.

                User Question:
                $prompt
            ";

            $response=Http::connectTimeout(120)
                    ->timeout(120)
                    ->post($this->googleAiUrl.$this->googleAiApiKey,[
                        "systemInstruction"=>[
                            "parts"=>[
                                [
                                    "text"=>"$systemPrompt"
                                ]
                            ]
                        ],
                        "contents"=>[
                            [
                                "role"=>"user",
                                "parts"=>[
                                    [
                                        "text"=>$promptForImages,
                                    ],
                                    [
                                        "inlineData"=>[
                                            "mimeType"=>$file->getMimeType(),
                                            "data"=>base64_encode(file_get_contents($file->getRealPath()))
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]);

            if(!$response->successful()){
                return null;
            }
            return $response->json();
        }

        if(str_starts_with($fileType,'application/') || str_starts_with($fileType,'text/')){
            $text=$this->documentExtractor->extract($file);

            if(blank($text)){
                throw new RuntimeException(
                    "Unable to extract readable text from document"
                );
            }

            $newPrompt=<<<PROMPT
                        The following text was extracted from a document uploaded by the user.

                        Instructions:
                        - Answer using ONLY the provided document.
                        - Do not use outside knowledge unless the user explicitly asks.
                        - If the answer is not found in the document, say:
                        "I couldn't find that information in the provided document."
                        - When possible, quote or summarize the relevant part before answering.

                        ----- DOCUMENT -----

                        $text

                        ----- END DOCUMENT -----

                        User Question:

                        $prompt
            PROMPT;

            return $this->generate($newPrompt,$user);
        }
        throw new InvalidArgumentException("Unsupported file type: $fileType");
    }
}