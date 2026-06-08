<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

class DocumentExtractorService{

    public function extractPdf(string $path):string{
        $parser=new Parser();
        $pdf=$parser->parseFile($path);
        return $pdf->getText();
    }

    public function extractDocx(string $path):string{
        $parser=IOFactory::load($path);
        $text='';
        foreach($parser->getSections() as $section){
            foreach($section->getElements() as $element){
                if(method_exists($element,'getText')){
                    $text.=$element->getText().'\n';
                }
            }
        }
        return $text;
    }

    public function extractTxt(string $path):string{
        return file_get_contents($path);
    }

    public function extract(UploadedFile $file):string{
        $mimeType=$file->getMimeType();

        return match($mimeType){
            'application/pdf'
                =>$this->extractPdf($file->getRealPath()),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                =>$this->extractDocx($file->getRealPath()),
            'text/plain'
                =>$this->extractTxt($file->getRealPath()),
            default
                => throw new InvalidArgumentException("Unsupported document type: $mimeType")
        };
    }
}