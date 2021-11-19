<?php
$baseUrl = 'https://pcc.g0v.ronny.tw/api/searchbytitle?query=%E8%87%BA%E5%8D%97%E5%B8%82%E6%94%BF%E5%BA%9C&page=';
$units = [];
for ($no = 1; $no <= 101; $no++) {
    $pageFile = __DIR__ . '/pages/page_' . $no . '.json';
    if (file_exists($pageFile)) {
        $json = json_decode(file_get_contents($pageFile), true);
    } else {
        $json = json_decode(file_get_contents($baseUrl . $no), true);
        file_put_contents($pageFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    foreach ($json['records'] as $record) {
        if (false !== strpos($record['unit_name'], '臺南市')) {
            $units[$record['unit_name']] = $record['unit_id'];
        }
    }
}

$oFh = fopen(__DIR__ . '/result.csv', 'w');
fputcsv($oFh, ['年度', '單位', '案件', '廠商', '廠商設立日', '結標日', '設立到結標天數', '廠商資本額', '標案金額', '標案金額/資本額', '網址']);
$count = 0;
$pool = [];

foreach ($units as $unitName => $unitId) {
    $recordFile = __DIR__ . '/unit/' . $unitName . '.json';
    if (!file_exists($recordFile)) {
        file_put_contents($recordFile, file_get_contents('https://pcc.g0v.ronny.tw/api/listbyunit?unit_id=' . $unitId));
    }
    $records = json_decode(file_get_contents($recordFile), true);
    foreach ($records['records'] as $record) {
        if (substr($record['date'], 0, 4) < 2019) {
            continue;
        }
        if(!isset($pool[$record['tender_api_url']])) {
            $pool[$record['tender_api_url']] = true;
        } else {
            continue;
        }
        $unitPath = __DIR__ . '/case/' . $record['unit_name'];
        if (!file_exists($unitPath)) {
            mkdir($unitPath, 0777, true);
        }
        $caseFile = $unitPath . '/' . $record['job_number'] . '.json';
        if (!file_exists($caseFile)) {
            $record['tender_api_url'] = str_replace('unit=', 'unit_id=', $record['tender_api_url']);
            file_put_contents($caseFile, file_get_contents($record['tender_api_url']));
        }
        $case = json_decode(file_get_contents($caseFile), true);
        foreach ($case['records'] as $period) {
            if ('決標公告' === $period['brief']['type']) {
                if (!isset($period['detail']['已公告資料:標案名稱'])) {
                    $period['detail']['已公告資料:標案名稱'] = $period['detail']['採購資料:標案名稱'];
                }
                if (!isset($period['detail']['決標品項:第1品項:得標廠商1:得標廠商'])) {
                    continue;
                }
                $y = substr($period['date'], 0, 4);
                $periodParts = [];
                $periodTime = 0;
                if (isset($period['detail']['已公告資料:開標時間'])) {
                    $periodParts = explode(' ', $period['detail']['已公告資料:開標時間']);
                } elseif (isset($period['detail']['採購資料:開標時間'])) {
                    $periodParts = explode(' ', $period['detail']['採購資料:開標時間']);
                }
                $timeParts = explode('/', $periodParts[0]);
                if (count($timeParts) !== 3) {
                    continue;
                }
                $periodTime = mktime(0, 0, 0, $timeParts[1], $timeParts[2], $timeParts[0] + 1911);
                $vendors = [];
                foreach ($period['detail'] as $k => $v) {
                    $parts = explode(':', $k);
                    if (count($parts) === 3 && $parts[0] === '投標廠商') {
                        if (!isset($vendors[$parts[1]])) {
                            $vendors[$parts[1]] = [];
                        }
                        $vendors[$parts[1]][$parts[2]] = $v;
                    }
                }
                $amount = intval(preg_replace('/[^0-9]/', '', $period['detail']['決標資料:總決標金額']));
                foreach ($vendors as $vendor) {
                    if (!empty($vendor['廠商代碼']) && $vendor['是否得標'] === '是') {
                        $vendorPath = __DIR__ . '/vendor';
                        if (!file_exists($vendorPath)) {
                            mkdir($vendorPath, 0777, true);
                        }
                        $vendorFile = $vendorPath . '/' . $vendor['廠商代碼'] . '.json';
                        if (!file_exists($vendorFile)) {
                            file_put_contents($vendorFile, file_get_contents('http://gcis.nat.g0v.tw/api/show/' . $vendor['廠商代碼']));
                        }
                        $vendorJson = json_decode(file_get_contents($vendorFile), true);
                        $vendorTime = 0;
                        if('40816629' === $vendor['廠商代碼']) {
                            $vendorJson['data']['核准設立日期'] = [
                                'year' => '2014',
                                'month' => '12',
                                'day' => '10',
                            ];
                        }
                        if(isset($vendorJson['data']['資本總額(元)'])) {
                            $vendorAsset = $vendorJson['data']['資本總額(元)'];
                        } elseif(isset($vendorJson['data']['資本額(元)'])) {
                            $vendorAsset = $vendorJson['data']['資本額(元)'];
                        }
                        $vendorAsset = str_replace(',', '', $vendorAsset);
                        if (isset($vendorJson['data']['核准設立日期'])) {
                            $vendorTime = mktime(0, 0, 0, $vendorJson['data']['核准設立日期']['month'], $vendorJson['data']['核准設立日期']['day'], $vendorJson['data']['核准設立日期']['year']);
                        } elseif (isset($vendorJson['data']['核准登記日期'])) {
                            $vendorTime = mktime(0, 0, 0, $vendorJson['data']['核准登記日期']['month'], $vendorJson['data']['核准登記日期']['day'], $vendorJson['data']['核准登記日期']['year']);
                        }
                        if ($vendorTime > 0) {
                            $days = ($periodTime - $vendorTime) / 86400;
                            fputcsv($oFh, [
                                $y,
                                $period['detail']['機關資料:機關名稱'],
                                $period['detail']['已公告資料:標案名稱'],
                                $vendor['廠商名稱'],
                                date('Y-m-d', $vendorTime),
                                date('Y-m-d', $periodTime),
                                $days,
                                $vendorAsset,
                                $amount,
                                round(intval($amount) / intval($vendorAsset), 2),
                                $period['detail']['url']
                            ]);
                        }
                    }
                }
            }
        }
    }
}
