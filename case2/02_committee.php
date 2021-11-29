<?php
$unitFile = __DIR__ . '/unit/unit.json';
$units = json_decode(file_get_contents($unitFile), true);
$count = [];
$pool = [];
$check = [];
$committeePath = __DIR__ . '/committee';
if(!file_exists($committeePath)) {
    mkdir($committeePath, 0777, true);
}

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
                if (!isset($period['detail']['已公告資料:標案名稱'])) {
                    $period['detail']['已公告資料:標案名稱'] = $period['detail']['採購資料:標案名稱'];
                }
                $caseType = '';
                if(isset($period['detail']['已公告資料:決標方式'])) {
                    $caseType = $period['detail']['已公告資料:決標方式'];
                } elseif(isset($period['detail']['採購資料:決標方式'])) {
                    $caseType = $period['detail']['採購資料:決標方式'];
                }
                if(!isset($count[$caseType])) {
                    $count[$caseType] = 0;
                }
                ++$count[$caseType];
                if (isset($period['detail']['最有利標:評選委員'])) {
                    $y = substr($period['date'], 0, 4);
                    
                    if(!isset($period['detail']['決標資料:總決標金額'])) {
                        continue;
                    }
                    $amount = intval(preg_replace('/[^0-9]/', '', $period['detail']['決標資料:總決標金額']));
                    foreach ($period['detail']['最有利標:評選委員'] as $members) {
                        foreach ($members as $member) {
                            $key = $member['姓名'] . '_' . $member['職業'];
                            $key = str_replace(['/', ' '], '_', $key);
                            $memberFile = $committeePath . '/' . $key . '.csv';
                            if(!file_exists($memberFile)) {
                                $fh = fopen($memberFile, 'w');
                                fputcsv($fh, ['year', 'unit', 'case', 'amount', 'url']);
                            } else {
                                $fh = fopen($memberFile, 'a');
                            }
                            fputcsv($fh, [
                                $y,
                                $period['detail']['機關資料:機關名稱'],
                                $period['detail']['已公告資料:標案名稱'],
                                $amount,
                                $period['detail']['url']
                            ]);
                            fclose($fh);
                            if (!isset($pool[$key])) {
                                $pool[$key] = [
                                    'key' => $key,
                                    'count' => 0,
                                    'amount' => 0,
                                ];
                            }
                            if(!isset($pool[$key][$y])) {
                                $pool[$key][$y] = [];
                            }
                            if(!isset($pool[$key][$y][$unitName])) {
                                $pool[$key][$y][$unitName] = 0;
                            }
                            ++$pool[$key][$y][$unitName];
                            ++$pool[$key]['count'];
                            $pool[$key]['amount'] += $amount;
                        }
                    }
                }
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
usort($pool, 'cmp');
print_r($count);
print_r($pool);
