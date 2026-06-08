<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

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

    public function generate(string $prompt){
        
        // Google Ai response
        $response=Http::connectTimeout(120)
                ->timeout(120)
                ->post($this->googleAiUrl.$this->googleAiApiKey,[
                    "contents"=>[
                        "parts"=>[
                            "text"=>$prompt
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

    public function generateWithFile(string $prompt,UploadedFile $file){
        $fileType=$file->getMimeType();

        if(str_starts_with($fileType,'image/')){
            $response=Http::connectTimeout(120)
                    ->timeout(120)
                    ->post($this->googleAiUrl.$this->googleAiApiKey,[
                        "contents"=>[
                            "parts"=>[
                                [
                                    "text"=>$prompt,
                                ],
                                [
                                    "inlineData"=>[
                                        "mimeType"=>$file->getMimeType(),
                                        "data"=>base64_encode(file_get_contents($file->getRealPath()))
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
            $newPrompt="$prompt\n\nDocument text:\n\n$text";
            return $this->generate($newPrompt);
        }
        throw new InvalidArgumentException("Unsupported file type: $fileType");
    }
}