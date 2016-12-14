<?php

namespace app\helpers;

class Csv
{
    
    public static function readFile($csvFile)
    {
        $rows = array();
        if (($handle = fopen($csvFile, "r")) !== false) {
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }
        return $rows;
    }
    
    /**
     * Returns associative array indexed by first value in row
     * 
     * @param string $csvFile
     * @return array
     */
    public static function readFileAssoc($csvFile)
    {
        $rows = array();
        if (($handle = fopen($csvFile, "r")) !== false) {
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                $rows[$data[0]] = $data;
            }
            fclose($handle);
        }
        return $rows;
    }
    
    /**
     * 
     * @param string $csvFile
     * @param string $firstValue
     * @return false|array
     */
    public static function findRowByFirstValue($csvFile, $firstValue)
    {
        $row = false;
        if (($handle = fopen($csvFile, "r")) !== false) {
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                if ($data[0] === $firstValue) {
                    $row = $data;
                    break;
                }
            }
            fclose($handle);
        }
        return $row;
    }
    
    /**
     * Writes rows to csv file
     * 
     * @param string $csvFile
     * @param array $rows
     */
    public static function writeFile($csvFile, $rows)
    {
        $handle = fopen($csvFile, "w");
        if ($handle !== false) {
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }
    }
    
    public static function addRow($csvFile, $row)
    {
        $handle = fopen($csvFile, "a");
        if ($handle !== false) {
            fputcsv($handle, $row);
            fclose($handle);
        }
    }
    
    /**
     * Returns associative array indexed by first value in row and contains second value only
     * 
     * @param string $csvFile
     * @return array
     */
    public static function loadAssocOneValue($csvFile)
    {
        $data = array();
        $rows = static::readFile($csvFile);
        foreach ($rows as $row) {
            $data[$row[0]] = $row[1];
        }
        return $data;
    }

}
