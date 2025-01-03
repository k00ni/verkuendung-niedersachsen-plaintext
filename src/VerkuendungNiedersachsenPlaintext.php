<?php

declare(strict_types=1);

namespace VerkuendungNiedersachsenPlaintext;

use Spatie\PdfToText\Pdf;

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

        $pdfParser = new Pdf();

        $pdfCacheFolderpath = __DIR__.'/../pdf_cache/';
        $plaintextFolderpath = __DIR__.'/../plaintext/';

        echo PHP_EOL;

        foreach ($verkuendungenArr as $key => $entry) {
            echo '.';

            // make it a string for easier usage later on (see CSV metadata generation)
            $verkuendungenArr[$key]['attachments'] = implode(',', $entry['attachments']);

            $urlToPdfDownload = $this->buildUrl($entry);

            // generate a full filename for the file itself
            $plaintextDocument = str_replace('.pdf', '.txt', $entry['mainDocment']);
            $localPdfFilepath = $pdfCacheFolderpath.$entry['mainDocment'];
            $localTxtFilepath = $plaintextFolderpath.$plaintextDocument;

            if (false === file_exists($localPdfFilepath)) {
                $result = file_put_contents($localPdfFilepath, file_get_contents($urlToPdfDownload));
                if (false === $result) {
                    // something went wrong while downloading the PDF file
                }
            }

            $text = $pdfParser->setPdf($localPdfFilepath)->text();

            // (re)generate plaintext file
            $plaintext = $this->enrichPlaintext($text);
            file_put_contents($localTxtFilepath, $plaintext);

            // used in CSV file generation
            $verkuendungenArr[$key]['relatedPlaintextFile'] = $plaintextDocument;
        }

        $this->generateCSVFileWithMetadata($verkuendungenArr);

        echo PHP_EOL;
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
        // remove all non-UTF8 characters
        $plaintext = iconv('UTF-8', 'UTF-8//IGNORE', $plaintext);

        // remove form feed, tells the printer to start a new page
        // (e.g. https://www.w3schools.com/jsref/jsref_regexp_formfeed.asp)
        $plaintext = preg_replace('/(\f+)/', '', $plaintext);

        // remove all tabs
        $plaintext = preg_replace('/\t/', ' ', $plaintext);

        // Remove multiple whitespaces
        $plaintext = preg_replace('/[[:blank:]]{2,}/', ' ', $plaintext);

        /*
         * Change all words, which have one part in a line, then a dash and then the rest of the word continues on the next line.
         * Remove the dash and line break, so the word is complete.
         * This produces long lines of clean text.
         *
         * Example:                       this
         *                                ||
         *                                ||
         *                                \/
         * [...] die Leitung der Stiftungs-
         * verwaltung hat die Rechtsstellung eines [...]
         */
        $regex = '/[a-zA-ZäöüßÄÖÜ]+\-\n[a-zA-ZüäöÜÄÖß]+/m';
        preg_match_all($regex, $plaintext, $matches);

        foreach ($matches[0] as $match) {
            $fixedString = $match;
            // remove line break
            $fixedString = preg_replace('/\n/', '', $fixedString);
            // remove the dash (-)
            $fixedString = str_replace('-', '', $fixedString);

            // now $fixedString should be a valid word

            $plaintext = str_replace($match, $fixedString, $plaintext);
        }

        // make sure that there are not more than 2 newlines after another
        $plaintext = preg_replace('/\n{2,}/', PHP_EOL. PHP_EOL, $plaintext);

        // fix broken dates such as "01. 01.2024" or "31. 12. 2024"
        $plaintext = preg_replace('/([0-9]{2})\.\s*([0-9]{2})\.\s*([0-9]{4})/m', '${1}.${2}.${3}', $plaintext);

        /*
         * remove obsolete whitespaces at the beginning and the end of strings between „ and “
         *
         * example:
         *                                                                                                      this
         *                                                                                                      ||
         *                                                                                                      ||
         *                                                                                                      \/
         *      „– für die die Beihilfen die Anmeldeschwelle nach Artikel 4 Abs. 1 Buchst . s AGVO überschreiten, “
         */
        $plaintext = preg_replace('/„\s*(.*?)\s*“/m', '„${1}“', $plaintext);

        /*
         * remove line breaks between dash and first letter if line starts with a dash
         *
         * example:
         *
         * -ņ                       <---,--- these
         * \n                       <--´
         * für die Beihilfen
         */
        $plaintext = preg_replace('/^–\n+\s*(.*?)$/mi', '- ${1}', $plaintext);

        // the line before will produce lines with only "– –" sometimes
        $plaintext = preg_replace('/^– –$/mi', '–', $plaintext);

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
                $value = (string) ($entry[$name] ?? '');
                $value = str_replace('"', "'", $value);

                $lineEntries[] = '"'. trim($value).'"';
            }

            $fileContent .= implode(',', $lineEntries).PHP_EOL;
        }

        file_put_contents(__DIR__.'/../metadata.csv', $fileContent);
    }
}
