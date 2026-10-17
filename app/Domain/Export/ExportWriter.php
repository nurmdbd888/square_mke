<?php

namespace App\Domain\Export;

use Dompdf\Dompdf;
use Dompdf\Options;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

final class ExportWriter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|null>>  $rows
     */
    public function write(string $format, string $absolutePath, array $headers, iterable $rows): string
    {
        return match ($format) {
            'csv' => $this->csv($absolutePath, $headers, $rows),
            'xlsx' => $this->xlsx($absolutePath, $headers, $rows),
            'pdf' => $this->pdf($absolutePath, $headers, $rows),
            default => throw new RuntimeException("Unsupported format: {$format}"),
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|null>>  $rows
     */
    private function csv(string $path, array $headers, iterable $rows): string
    {
        $writer = new CsvWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return 'text/csv';
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|null>>  $rows
     */
    private function xlsx(string $path, array $headers, iterable $rows): string
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|null>>  $rows
     */
    private function pdf(string $path, array $headers, iterable $rows): string
    {
        $html = view('export.print-table', ['headers' => $headers, 'rows' => $rows])->render();
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        file_put_contents($path, $dompdf->output());

        return 'application/pdf';
    }
}
