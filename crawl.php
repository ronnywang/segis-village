<?php

ini_set('memory_limit', '1g');
// docker run -p 5432:5432 --name some-postgis -e POSTGRES_PASSWORD= -d mdillon/postgis
$dbh = new PDO('pgsql:host=localhost user=postgres password=');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
file_put_contents("history.jsonl", '');
$add_history = function($act, $time, $id, $data){
    file_put_contents('history.jsonl', json_encode(array('act' => $act, 'time' => $time, 'id' => $id, 'data' => $data), JSON_UNESCAPED_UNICODE). "\n", FILE_APPEND);
};

$output_geojsons = array();
$village_geojsons = array();

$add_geojson = function($content, $note) use (&$output_geojsons) {
    $id = count($output_geojsons);
    $output_geojsons[] = $note;
    file_put_contents("output_geojsons/{$id}.json", $content);
    return $id;
};

// ret=[],$('img[title="取得下載此項產品檔的網址"]').each(function(){  var id =$(this).attr('data-id');  ret.push([id,sirc.pageEncrypt.Encrypt(id + '_0', 'pageencryptkey')]); }); console.log(JSON.stringify(ret  ))
$codes = json_decode('[["130502","4E787F33AEEBC368741D466F0EDAE042"],["126308","0B0E628E48E3C9AFF68B0C6734DEAD96"],["124400","2EEA0004CDDFB0E1130AD88CE8ED930F"],["121348","BACF26E3C3F0E0CF16A29E37333D0688"],["119712","61350A86BCD3F2DD68C87869AA3448C9"],["113775","436ABDE5C5F32DB542C5C56A3F604041"],["112184","13C7A05078CD7045BBB70E111E808C7C"],["107687","0081D31803491EBE0A6CD7B3A9B2E0EC"],["106642","2A5DBECB8850CD46A000886C5C9B6AC9"],["98360","9634548B22E1F137C7CB7B30B968C6C3"],["92079","1FD333094CA099776011CBC80C4B1C80"],["86899","844D13CF6620DF0F6011CBC80C4B1C80"],["82833","78243D31DBCE8B685D2A0E78C8526388"],["61057","03CA9B57400DE535F909BACA6B291D89"],["83754","1CA36F0A7CFDDFD7B5B8238FD967B78B"],["83760","5E35E6324C2E3E90C7CB7B30B968C6C3"],["83759","1CA36F0A7CFDDFD76011CBC80C4B1C80"],["83773","D8D5D14C16BA58485D2A0E78C8526388"],["83776","D8D5D14C16BA5848FF293498F056F917"],["83775","D8D5D14C16BA58484F61F15192CDA19F"],["83774","D8D5D14C16BA5848B5B8238FD967B78B"],["83769","5E35E6324C2E3E906011CBC80C4B1C80"],["83772","D8D5D14C16BA5848C3C6ABC031BA48CF"],["83771","D8D5D14C16BA584808CBC5524C6901BF"],["83770","D8D5D14C16BA5848C7CB7B30B968C6C3"],["83765","5E35E6324C2E3E904F61F15192CDA19F"],["83768","5E35E6324C2E3E90CF321F65F15A81A2"],["83767","5E35E6324C2E3E90F909BACA6B291D89"],["83766","5E35E6324C2E3E90FF293498F056F917"],["83761","5E35E6324C2E3E9008CBC5524C6901BF"],["83764","5E35E6324C2E3E90B5B8238FD967B78B"],["83763","5E35E6324C2E3E905D2A0E78C8526388"],["83762","5E35E6324C2E3E90C3C6ABC031BA48CF"],["83785","718C88970C77E6734F61F15192CDA19F"],["83788","718C88970C77E673CF321F65F15A81A2"],["83787","718C88970C77E673F909BACA6B291D89"],["83786","718C88970C77E673FF293498F056F917"],["83781","718C88970C77E67308CBC5524C6901BF"],["83784","718C88970C77E673B5B8238FD967B78B"],["83783","718C88970C77E6735D2A0E78C8526388"],["83782","718C88970C77E673C3C6ABC031BA48CF"],["83777","D8D5D14C16BA5848F909BACA6B291D89"],["83780","718C88970C77E673C7CB7B30B968C6C3"],["83779","D8D5D14C16BA58486011CBC80C4B1C80"],["83778","D8D5D14C16BA5848CF321F65F15A81A2"]]');

$code_map = new StdClass;
foreach ($codes as $k_v) {
    list($id, $code) = $k_v;
    $code_map->{$id} = $code;
}
foreach (json_decode(file_get_contents('list.json'))->rows as $row) {
    if (!preg_match('#(\d*)Y(\d*)M#', $row->STTIME, $matches)) {
        print_r($row);
        throw new Exception('error STTIME');
    }
    $year = $matches[1];
    $month = $matches[2];

    if (!property_exists($code_map, $row->ID)) {
        print_r($row);
        continue;
    }
    $code = $code_map->{$row->ID};
    $target = __DIR__ . sprintf('/files/%03d%02d.zip', $year, $month);

    if (!file_exists($target)) {
        $url = "https://segis.moi.gov.tw/STAT/Generic/Project/GEN_STAT.ashx?method=downloadproductfile&code={$code}&STTIME=" . sprintf("%03dY%02d", $year, $month) . "&STUNIT=U01VI&BOUNDARY=全國&SUBBOUNDARY=";
        system(sprintf("curl %s > %s", escapeshellarg($url), escapeshellarg($target)));
    }

    $shp_target = __DIR__ . sprintf('/shp/%03d%02d.zip', $year, $month);
    if (!file_exists($shp_target)) {
        $zip = zip_open($target);
        while ($file = zip_read($zip)) {
            if (strpos(zip_entry_name($file), '_SHP.zip')) {
                file_put_contents($shp_target, zip_entry_read($file, zip_entry_filesize($file)));
            }
        }
    }

    $geojson_target = __DIR__ . sprintf('/geojson/%03d%02d.geojson', $year, $month);
    if (!file_exists($geojson_target . '.gz')) {
        $zip = zip_open($shp_target);
        mkdir("tmp");
        while ($file = zip_read($zip)) {
            file_put_contents("tmp/" . zip_entry_name($file), zip_entry_read($file, zip_entry_filesize($file)));
        }
        system(sprintf("ogr2ogr -f GeoJSON %s tmp/*.SHP", escapeshellarg($geojson_target)));
        system("gzip " . escapeshellarg($geojson_target));
        system("rm -rf tmp");
    }
}

$files = glob("geojson/*.gz");
sort($files);
$current_villages = array();
$current_yyymm = null;
$prev_villages = null;
$prev_yyymm = null;

foreach ($files as $file) {
    preg_match('#geojson/(\d+)\.geojson.gz#', $file, $matches);
    $current_yyymm = $yyymm = $matches[1];
//    if ($yyymm != 10712) continue;

    $sql = "CREATE TABLE village_{$yyymm} (id CHAR(16), geo GEOMETRY(MultiPolygon, 3826), geo_md5 CHAR(32), PRIMARY KEY (id) )";
    try {
        $created = false;
        $dbh->exec($sql);
    } catch (Exception $e) {
        $created = true;
    }

    if (!$created) {
        $sql = "CREATE INDEX village_{$yyymm}_geo ON village_{$yyymm} USING GIST(geo)";
        $dbh->exec($sql);
    }

    error_log($file);
    $lineno = 0;
    $fp = gzopen($file, 'r');
    $terms = array();
    while (false !== ($line = fgets($fp))) {
        $lineno ++;
        if (strpos($line, '{ "type": "Feature"') !== 0) {
            continue;
        }
        $utf_line = str_replace('\\', '', iconv('Big5', 'UTF-8//IGNORE', $line));
        if (!$obj = json_decode($utf_line)) {
            throw new Exception("failed on {$file} line: {$lineno}");
        }
        $id = $obj->properties->V_ID;
        unset($obj->properties->H_CNT);
        unset($obj->properties->P_CNT);
        unset($obj->properties->M_CNT);
        unset($obj->properties->F_CNT);
        unset($obj->properties->INFO_TIME);
        if (!$id) {
            continue;
        }
        $current_villages[$id] = $obj->properties;
        $move_to = array();

        if ($obj->geometry->type == 'Polygon') {
            $obj->geometry = array('type' => 'MultiPolygon', 'coordinates' => array($obj->geometry->coordinates));
        }
        if (!$created) {
            $terms[] = sprintf("('%s',ST_SetSRID(ST_GeomFromGeoJSON('%s'),3826), '%s')", $id, json_encode($obj->geometry), md5(json_encode($obj->geometry)));
            if (count($terms) > 1000) {
                $sql = "INSERT INTO village_{$yyymm} (id,geo,geo_md5) VALUES " . implode(',', $terms);
                $dbh->exec($sql);
                $terms = array();
            }
        }
    }
    if ($terms) {
        $sql = "INSERT INTO village_{$yyymm} (id,geo,geo_md5) VALUES " . implode(',', $terms);
        $dbh->exec($sql);
    }

    if (!is_null($prev_villages)) {
        $change = false;
        $prev_id = array_combine(array_keys($prev_villages), array_fill(0, count($prev_villages), 100));
        $current_id = array_combine(array_keys($current_villages), array_fill(0, count($current_villages), 100));
        error_log(sprintf("prev_id_count = %d, current_id_count = %d", count($prev_id), count($current_id)));
        $sql = "SELECT village_{$current_yyymm}.id FROM village_{$prev_yyymm} JOIN village_{$current_yyymm} USING(id) WHERE village_{$prev_yyymm}.geo_md5 = village_{$current_yyymm}.geo_md5";
        $stmt = $dbh->prepare($sql);
        $stmt->execute();
        error_log('只看 MD5 相同 done');
        $i = 0;
        $skip_ids = array();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $id = trim($row[0]);
            unset($prev_id[$id]);
            unset($current_id[$id]);
            if ($current_villages[$id] != $prev_villages[$id]) {
                $add_history('data-change', $current_yyymm, $id, array('old' => $prev_villages[$id], 'new' => $current_villages[$id]));
            }
            $skip_ids[] = "'{$id}'";
        }
        $stmt->closeCursor();

        $sql =  "SELECT id";
        $sql .= "       , ST_Area(geo)";
        $sql .= "       , (SELECT JSON_AGG(ARRAY[id, ";
        $sql .= "                                ST_Area(geo)::text,";
        $sql .= "                                ST_Area(ST_Difference(geo, (SELECT geo FROM village_{$current_yyymm} WHERE id = village_{$prev_yyymm}.id)))::text, ";
        $sql .= "                                CASE WHEN id = village_{$current_yyymm}.id THEN ";
        $sql .= "                                    ST_Area(ST_Intersection(geo, village_{$current_yyymm}.geo))::text";
        $sql .= "                                ELSE ";
        $sql .= "                                    ST_Area(ST_Intersection(ST_Difference(geo, (SELECT geo FROM village_{$current_yyymm} WHERE id = village_{$prev_yyymm}.id)), village_{$current_yyymm}.geo))::text";
        $sql .= "                                END,";
        $sql .= "                                ST_Area(ST_Intersection(geo, (SELECT geo FROM village_{$current_yyymm} WHERE id = village_{$prev_yyymm}.id)))::text,";
        $sql .= "                                ST_Area(ST_Intersection(geo, village_{$current_yyymm}.geo))::text,";
        $sql .= "                                ST_AsGeoJSON(ST_Transform(ST_Intersection(geo, village_{$current_yyymm}.geo), 4326)) ";
        $sql .= "                         ])";
        $sql .= "             FROM village_{$prev_yyymm} ";
        $sql .= "             WHERE ST_DWithIn(geo, village_{$current_yyymm}.geo, 0) ";
        $sql .= "                   AND id NOT IN (" . implode(',', $skip_ids) . ")";
        $sql .= "         )";
        $sql .= "         , ST_AsGeoJSON(ST_Transform(geo, 4326))";
        $sql .= "  FROM village_{$current_yyymm} ";
        $sql .= " WHERE id NOT IN (" . implode(',', $skip_ids) . ") ";
        // 有前手，開始測試
        $stmt = $dbh->prepare($sql);
        $stmt->execute();
        error_log('done');
        $i = 0;
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            //     [0] => 10004060-010
            //     [1] => 1227608.37104416
            //     [2] => [["10004060-010    ","2580828.43584107","1353220.14835767","8.53400860821037e-05","1227608.2874834"],
            //     ["10004060-008    ","3102057.90116136","1706620.6561264","0.0265248317159613","1395437.24503495"]]
            list($id, $area, $inter_villages, $new_geojson) = $row;
            $id = trim($id);
            $inter_villages = json_decode($inter_villages);
            foreach ($inter_villages as $idx => $inter_village) {
                // 0-id
                // 1-完整面積 $full_area
                // 2-減少的面積 $village_area (null 表示全減)
                // 3-減少的面積有多少是給新區 $inter_area (null 改抓 5)
                // 4-保留的面積 $reserved_area (null 表示 0)
                list($inter_id, $full_area, $village_area, $inter_area, $reserved_area) = $inter_village;
                $inter_villages[$idx][0] = trim($inter_village[0]);
                $inter_villages[$idx][1] = floatval($inter_village[1]);
                $inter_villages[$idx][2] = is_null($inter_village[2]) ? floatval($inter_village[1]) : floatval($inter_village[2]);
                $inter_villages[$idx][3] = is_null($inter_village[3]) ? floatval($inter_village[5]) : floatval($inter_village[3]);
                $inter_villages[$idx][4] = floatval($inter_village[4]);
            }
            print_r(array($id, $area, json_encode(array_map(function($d) {
                return array($d[0], $d[1], $d[2], $d[3], $d[4]);
            }, $inter_villages))));

            $geo_from = array();
            foreach ($inter_villages as $inter_village) {
                list($inter_id, $full_area, $village_area, $inter_area, $reserved_area, , $geojson) = $inter_village;
                if (trim($inter_id) == $id) {
                    $inter_area = $reserved_area;
                } else if ($inter_area < 0.002 * $village_area or $village_area == 0) {
                    continue;
                }
                // 0-id
                // 1-完整面積 $full_area
                // 2-減少的面積 $village_area
                // 3-減少的面積有多少是給新區 $inter_area
                // 4-保留的面積 $reserved_area
                if (!array_key_exists($inter_id, $prev_id)) {
                    //echo $inter_id;
                    //exit;
                }
                $prev_id[$inter_id] -= 100 * $inter_area / $full_area;
                if (!array_key_exists($inter_id, $move_to)) {
                    $move_to[$inter_id] = array();
                }
                $move_rate = round($inter_area / $village_area, 3);
                $geoid = null;
                if ($move_rate == 1) {
                    $move_to[$inter_id][] = array($id, $move_rate, $village_geojsons[$inter_id]);
                } elseif ($move_rate) {
                    $geoid = $add_geojson($geojson, 'move-to-' . $inter_id);
                    $move_to[$inter_id][] = array($id, $inter_area / $village_area, $geoid);
                }

                if (round($prev_id[$inter_id]) <= 0) {
                    if (!$reserved_area) {
                        $add_history('remove', $current_yyymm, $inter_id, array('move-to' => $move_to[$inter_id]));
                    }
                    unset($prev_id[$inter_id]);
                }
                if ($inter_id == $id) {
                    $move_rate = $reserved_area / $full_area;
                } else {
                    $move_rate = $inter_area / $full_area;
                }
                $move_rate = round($move_rate, 3);

                if ($move_rate == 1) {
                    $geo_from[] = array($inter_id, $move_rate, $village_geojsons[$inter_id]);
                } elseif ($move_rate) {
                    if (is_null($geoid)) {
                        $geoid = $add_geojson($geojson, 'move-to-'. $inter_id);
                    }
                    $geo_from[] = array($inter_id, $move_rate, $geoid);
                }
            }

            // 如果 ID 是新出現的，表示新增村里
            if (array_key_Exists($id, $current_villages) and !array_key_exists($id, $prev_villages)) {
                $geoid = $add_geojson($new_geojson, 'village-' . $id);
                $village_geojsons[$id] = $geoid;
                $add_history('add', $current_yyymm, $id, array('data' => $current_villages[$id], 'geo_from' => $geo_from, 'geoid' => $geoid));
            } else {
                if (count($geo_from) != 1 or $geo_from[0] != $id) {
                    $geoid = $add_geojson($new_geojson, 'village-' . $id);
                    $village_geojsons[$id] = $geoid;
                    $add_history('geo-change', $current_yyymm, $id, array('geo_from' => $geo_from, 'geoid' => $geoid));
                }
            }

            unset($current_id[$id]);

            if (array_key_Exists($id, $prev_villages)) {
                if ($current_villages[$id] != $prev_villages[$id]) {
                    $add_history('data-change', $current_yyymm, $id, array('old' => $prev_villages[$id], 'new' => $current_villages[$id]));
                }
            }
        }
        if ($prev_id or $current_id) {
            echo "prev_id=" . json_encode($prev_id) . "\n";
            echo "current_id=" . json_encode($current_id) . "\n";
        }
        foreach ($prev_id as $inter_id => $data) {
            $add_history('remove', $current_yyymm, $inter_id, array('move-to' => array('unknown' => $data / 100)));
        }
        error_log("count {$i} finished");
        $stmt->closeCursor();
    } else {

        foreach (array_chunk($current_villages, 100, true) as $part_current_villages){
            $sql = "SELECT id, ST_AsGeoJSON(ST_Transform(geo, 4326)) FROM village_{$current_yyymm} WHERE id IN (" . implode(',', array_map(function($s){ return "'{$s}'";}, array_keys($part_current_villages))) . ")";
            $stmt = $dbh->prepare($sql);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                list($id, $geojson) = $row;
                $id = trim($id);
                $geojson_id = $add_geojson($geojson, "add-{$id}");
                $village_geojsons[$id] = $geojson_id;
            }
            $stmt->closeCursor();
            foreach ($part_current_villages as $id => $village) {
                $add_history('init', $current_yyymm, $id, array('data' => $village, 'geoid' => $village_geojsons[$id]));
            }
        }
    }
    $prev_villages = $current_villages;
    $prev_yyymm = $current_yyymm;
    $current_villages = array();
}
