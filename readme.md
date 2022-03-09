# sphinx-enum

Simple script to enumerate public sphinx servers, and it's contents.

### Usage
````
Usage: php sphinx-enum.php -target=(host or file) [-p=9307] [-e] [-d] [-m] [-i] [-loot=dir]
       php sphinx-enum.php -h for help

      -h - this help
-target= - host, ip or file with host list
      -p - port, default 9306
      -m - get server meta information
      -e - enum tables/indexes
      -d - describe index structure, requires -e
      -i - get index meta information, requires -e
  -loot= - directory to save index contents, dont save if not specified 
           [!] files will be overwritten
 -limit= - limit row count for looting for each index, default - 0 (loot all index)
 -batch= - set batch size for looting, default=1000
````