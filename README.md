# segis-village

用法
----
* php crawl.php
  * 本程式執行動作如下：
    1. 從 list.json 裡的 [社會經濟資料庫](https://segis.moi.gov.tw/STAT/Web/Portal/STAT_PortalHome.aspx) 「行政區人口統計\_村里」，抓取從 97年3月 到 108年3月 的人口統計資料集（含資料和地理圖資），存到 files/ 資料夾下
    2. 將資料集解壓縮出來，並且把 shapefile 解壓縮到 shp/ 資料夾下
    3. 使用 gdal 的 ogr2ogr ，把 shapefile 轉換成 geojson ，存到 geojson/ 資料夾下
       * 需要安裝 gdal ，有 ogr2ogr 指令可以使用
    4. 把 geojson 逐季塞入 postgresql 資料庫中
       * 可以用 docker run -p 5432:5432 --name some-postgis -e POSTGRES_PASSWORD= -d mdillon/postgis 建立一個暫時的 docker 資料庫
    5. 逐季做資料比對，並把發現變化存入 history.jsonl ，把變化的地理資料存入 output\_geojsons/ 資料夾下

授權
----
程式碼以 BSD License 授權
