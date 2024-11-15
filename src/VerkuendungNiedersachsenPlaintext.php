<?php

declare(strict_types=1);

namespace VerkuendungNiedersachsenPlaintext;

use Smalot\PdfParser\Parser;

class VerkuendungNiedersachsenPlaintext
{
    public function run(): void
    {
        /*
         * Note:
         * make sure setting "allow_url_fopen" is set to true
         * (https://www.php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen)
         * or use curl to get the content.
         */
        $rawContent = file_get_contents('https://www.verkuendung-niedersachsen.de/pubStore');

        $verkuendungenArr = json_decode($rawContent, true);

        if (false === is_array($verkuendungenArr)) {
            throw new \Error('We expect an array here, probably the content retrieval failed.');
        }

        $pdfParser = new Parser();
        $pdfCacheFolderpath = __DIR__.'/../pdf_cache/';
        $plaintextFolderpath = __DIR__.'/../plaintext/';

        echo PHP_EOL;

        foreach ($verkuendungenArr as $key => $entry) {
            echo '.';

            // make it a string for easier usage later on (see CSV metadata generation)
            $verkuendungenArr[$key]['attachments'] = implode(',', $entry['attachments']);

            $urlToPdfDownload = $this->buildUrl($entry);

            // generate a full filename for the file itself
            $plaintextDocument = $this->generateFilename($entry);
            $localPdfFilepath = $pdfCacheFolderpath.$entry['mainDocment'];
            $localTxtFilepath = $plaintextFolderpath.$plaintextDocument;

            if (false === file_exists($localPdfFilepath)) {
                $result = file_put_contents($localPdfFilepath, file_get_contents($urlToPdfDownload));
                if (false === $result) {
                    // something went wrong while downloading the PDF file
                }
            }

            $document = $pdfParser->parseFile($localPdfFilepath);

            if (file_exists($localTxtFilepath)) {
                // unlink($localTxtFilepath);
            } else {
                $plaintext = $this->enrichPlaintext($document->getText());
                file_put_contents($localTxtFilepath, $plaintext);
            }

            // used in CSV file generation
            $verkuendungenArr[$key]['relatedPlaintextFile'] = $plaintextDocument;
        }

        $this->generateCSVFileWithMetadata($verkuendungenArr);

        echo PHP_EOL;
    }

    public function generateFilename(array $entry): string
    {
        return str_replace('.pdf', '.txt', $entry['mainDocment']);
    }

    /**
     * Builds an URL to related PDF.
     *
     * @param array $entry
     * @return string
     */
    public function buildUrl(array $entry): string
    {
        $proclamationNr = $entry['proclamation_nr'];
        $year = (new \DateTime($entry['date']))->format('Y');

        $urlToPdfDownload = 'https://www.verkuendung-niedersachsen.de/api/ndsmbl/'.$year.'/'.$proclamationNr.'/0/';
        $urlToPdfDownload .= $entry['mainDocment'];

        return $urlToPdfDownload;
    }

    public function enrichPlaintext(string $plaintext): string
    {
        // remove all tabs
        $plaintext = preg_replace('/\t/', ' ', $plaintext);

        // Remove multiple whitespaces
        $plaintext = preg_replace('/[[:blank:]]{2,}/', ' ', $plaintext);

        // Remove trailing whitespaces each line
        $str = '';
        foreach (explode(PHP_EOL, $plaintext) as $line) {
            $str .= trim($line).PHP_EOL;
        }

        $plaintext = $str;

        return $plaintext;
    }

    public function generateCSVFileWithMetadata(array $verkuendungenArr): void
    {
        $columnNames = [
            'relatedPlaintextFile',
            'mainDocment',
            'fundstelle',
            'proclamation_nr',
            'title',
            'short',
            'abbreviation',
            'issuing_office',
            'issuing_date',
            'outline_nr',
            'date',
            'leadership',
            'proclamation_sheet',
            'proclamationType',
            'attachments'
        ];

        $fileContent = implode(',', $columnNames).PHP_EOL;

        // sort by proclamation_nr
        usort($verkuendungenArr, function($a, $b) {
            return $a['proclamation_nr'] < $b['proclamation_nr'] ? 1 : -1;
        });

        foreach ($verkuendungenArr as $entry) {
            $lineEntries = [];

            foreach ($columnNames as $name) {
                $value = $entry[$name] ?? '';
                $value = str_replace('"', "'", $value);

                $lineEntries[] = '"'. $value.'"';
            }

            $fileContent .= implode(',', $lineEntries).PHP_EOL;
        }

        file_put_contents(__DIR__.'/../metadata.csv', $fileContent);
    }
}
