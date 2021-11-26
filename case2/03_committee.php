<?php
$unitFile = __DIR__ . '/unit/unit.json';
$units = json_decode(file_get_contents($unitFile), true);
$count = [];
$pool = [];
$check = [];

foreach ($units as $unitId => $unitName) {
    if (substr($unitId, 0, 4) !== '3.95') {
        continue;
    }
    $recordFile = __DIR__ . '/unit/' . $unitName . '.json';
    $records = json_decode(file_get_contents($recordFile), true);
    foreach ($records['records'] as $record) {
        if (substr($record['date'], 0, 4) < 2019) {
            continue;
        }
        if (!isset($check[$record['tender_api_url']])) {
            $check[$record['tender_api_url']] = true;
        } else {
            continue;
        }
        $unitPath = __DIR__ . '/case/' . $record['unit_name'];
        if (!file_exists($unitPath)) {
            mkdir($unitPath, 0777, true);
        }
        $caseFile = $unitPath . '/' . $record['job_number'] . '.json';
        $case = json_decode(file_get_contents($caseFile), true);
        foreach ($case['records'] as $period) {
            if ('決標公告' === $period['brief']['type']) {
                $caseType = '';
                if(isset($period['detail']['已公告資料:決標方式'])) {
                    $caseType = $period['detail']['已公告資料:決標方式'];
                } elseif(isset($period['detail']['採購資料:決標方式'])) {
                    $caseType = $period['detail']['採購資料:決標方式'];
                }
                if('參考最有利標精神' === $caseType) {
                    echo $period['detail']['決標資料:附加說明'] . "\n\n";
                }
                if(!isset($count[$caseType])) {
                    $count[$caseType] = 0;
                }
                ++$count[$caseType];
                
            }
        }
    }
}
function cmp($a, $b)
{
    if ($a['count'] == $b['count']) {
        return 0;
    }
    return ($a['count'] > $b['count']) ? -1 : 1;
}
print_r($count);