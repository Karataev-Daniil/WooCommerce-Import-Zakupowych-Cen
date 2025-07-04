<?php

namespace WIPC;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class File_Parser {

    private $file_path;
    private $file_type;
    private $data = [];

    private $required_headers = ['nr. sku', 'cena_zakupu_za_1kg'];

    public function __construct( $file_path ) {
        $this->file_path = $file_path;
        $this->file_type = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
    }

    // Parse file based on type
    public function parse() {
        if ( $this->file_type === 'csv' ) {
            return $this->parse_csv();
        } elseif ( in_array( $this->file_type, [ 'xls', 'xlsx' ] ) ) {
            return $this->parse_excel();
        }

        throw new \Exception( 'Unsupported file format: ' . $this->file_type );
    }

    // Parse CSV file
    private function parse_csv() {
        $handle = fopen( $this->file_path, 'r' );

        if ( ! $handle ) {
            throw new \Exception( 'Cannot open CSV file.' );
        }

        $headers = fgetcsv( $handle, 1000, ',' );
        if ( ! $this->validate_headers( $headers ) ) {
            throw new \Exception( 'Missing required columns in CSV.' );
        }

        while ( ( $row = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
            $assoc = array_combine( $headers, $row );
            $this->data[] = $assoc;
        }

        fclose( $handle );

        return $this->data;
    }

    // Parse Excel file
    private function parse_excel() {
        if ( ! class_exists( IOFactory::class ) ) {
            throw new \Exception( 'PhpSpreadsheet not installed.' );
        }

        $spreadsheet = IOFactory::load( $this->file_path );
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $headers = array_map( 'trim', $rows[0] );
        if ( ! $this->validate_headers( $headers ) ) {
            throw new \Exception( 'Missing required columns in Excel.' );
        }

        for ( $i = 1; $i < count( $rows ); $i++ ) {
            $assoc = array_combine( $headers, $rows[$i] );
            $this->data[] = $assoc;
        }

        return $this->data;
    }

    // Check required headers presence
    private function validate_headers( $headers ) {
        foreach ( $this->required_headers as $required ) {
            if ( ! in_array( $required, $headers ) ) {
                return false;
            }
        }
        return true;
    }

    // Return limited preview of parsed data
    public function get_preview( $limit = 5 ) {
        return array_slice( $this->data, 0, $limit );
    }
}