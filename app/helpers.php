<?php

if (! function_exists('array_group_by') ) {
    function array_group_by(array $arr, callable $key_selector) {
        $result = array();
        foreach ($arr as $i) {
            $key = call_user_func($key_selector, $i);
            $result[$key][] = $i;
        }
        return $result;
    }
}

if (! function_exists('translateStatusCode')) {
    function translateStatusCode($code, $isShort = false){
        $length = $isShort ? '.short' : '.full';
        if(!isset($code)) return '';
        if(!intval($code)) $code = translateContractionToCode($code);
        if(!$code) return '';

        return trans('app.status_codes.' . $code . $length);
    }
}

if (! function_exists('translateContractionToCode')) {
    function translateContractionToCode($contraction){
        $codes = [
            "REK-TAR" => 31,
            "LUO" => 22,
            "LYR" => 56,
            "LAT" => 48,
            "JOT" => 71,
            "HYL" => 91,
            "TOI" => 77,
            "PET" => 38,
            "EDI" => 68,
            "LAN" => 13,
            "OUT" => 99,
            "SSI" => 45,
            "POI" => 20,
        ];
        if(isset($codes[$contraction])) return $codes[$contraction];
        else return false;
    }
}