<?php
$urls = [
    'https://pcc.g0v.ronny.tw/api/searchbycompanyid?query=53171755&page=1',
    'https://pcc.g0v.ronny.tw/api/searchbycompanyid?query=53171755&page=2',
];
$result = [];
foreach($urls AS $url) {
    $no = substr($url, -1);
    $pageFile = __DIR__ . '/page_' . $no . '.json';
    if(file_exists($pageFile)) {
        $json = json_decode(file_get_contents($pageFile), true);
    } else {
        $json = json_decode(file_get_contents($url), true);
        file_put_contents($pageFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    foreach($json['records'] AS $record) {
        $unitPath = __DIR__ . '/case/' . $record['unit_name'];
        if(!file_exists($unitPath)) {
            mkdir($unitPath, 0777, true);
        }
        $caseFile = $unitPath . '/' . $record['job_number'] . '.json';
        if(!file_exists($caseFile)) {
            $record['tender_api_url'] = str_replace('unit=', 'unit_id=', $record['tender_api_url']);
            file_put_contents($caseFile, file_get_contents($record['tender_api_url']));
        }
        $case = json_decode(file_get_contents($caseFile), true);
        foreach($case['records'] AS $period) {
            if('決標公告' === $period['brief']['type'] && $period['detail']['決標品項:第1品項:得標廠商1:得標廠商'] === '宏權科技有限公司') {
                $y = substr($period['date'], 0, 4);
                if(!isset($result[$y])) {
                    $result[$y] = [
                        'total' => 0,
                    ];
                }
                if(false !== strpos($period['detail']['機關資料:機關名稱'], '南市')) {
                    if(!isset($result[$y][$period['detail']['機關資料:機關名稱']])) {
                        $result[$y][$period['detail']['機關資料:機關名稱']] = [
                            'total' => 0,
                            'count' => 0,
                            'cases' => [],
                        ];
                    }
                    ++$result[$y][$period['detail']['機關資料:機關名稱']]['count'];
                    $amount = intval(preg_replace('/[^0-9]/', '', $period['detail']['決標資料:總決標金額']));
                    $result[$y][$period['detail']['機關資料:機關名稱']]['total'] += $amount;
                    $result[$y]['total'] += $amount;
                    if(!isset($period['detail']['已公告資料:標案名稱'])) {
                        $period['detail']['已公告資料:標案名稱'] = $period['detail']['採購資料:標案名稱'];
                    }
                    $result[$y][$period['detail']['機關資料:機關名稱']]['cases'][] = [
                        'date' => $period['date'],
                        'name' => $period['detail']['已公告資料:標案名稱'],
                        'url' => $period['detail']['url'],
                        'amount' => $amount,
                    ];
                }
            }
        }
    }
}
$oFh = fopen(__DIR__ . '/result.csv', 'w');
fputcsv($oFh, ['year', 'date', 'unit', 'name', 'amount', 'url']);
foreach($result AS $y => $data1) {
    foreach($data1 AS $unit => $data2) {
        if(isset($data2['cases'])) {
            foreach($data2['cases'] AS $case) {
                fputcsv($oFh, [$y, $unit, $case['date'], $case['name'], $case['amount'], $case['url']]);
            }
        }
    }
}