<?php
define('DEBUG', 0);
define('TEST', FALSE);

#var_dump(file_exists('NMS_REALITY_GCPRODUCTTABLE.EXML'));
#$fh = fopen('NMS_REALITY_GCPRODUCTTABLE.EXML', 'r');
#var_dump(fgets($fh));
#fclose($fh);

function printAttrs($node) {
    echo $node->nodeName;
    if(!$node->hasAttributes()) {
        echo "\n";
        return;
    }
    echo ' (';
    foreach($node->attributes as $attribute) {
        echo $attribute->nodeName.' = '.$attribute->nodeValue.', ';
    }
    echo ")\n";
}

$translationFiles = [
    'NMS_LOC1_ENGLISH.EXML',
    'NMS_LOC4_ENGLISH.EXML',
    'NMS_LOC5_ENGLISH.EXML',
    'NMS_UPDATE3_ENGLISH.EXML'
];

if(TEST) {
    $stringsRaw = [
        'FOOD_NOPE_L' => [],
        'FOOD_BLOB_VEG_NAME_L' => [],
        'NAMEGEN_WEAP_PROPERTY_25' => []
    ];
} else {
    $stringsRaw = [];
}

$itemsTransient = [];

##### List products #####

$reader = new XMLReader();
$reader->open('NMS_REALITY_GCPRODUCTTABLE.EXML');
while($reader->read()) {
    if($reader->nodeType !== XMLREADER::ELEMENT
      || $reader->localName !== 'Property'
      || $reader->getAttribute('value') !== 'GcProductData.xml') continue;

    $node = $reader->expand();
    $dom = new DomDocument();
    $dom->formatOutput = true;
    $n = $dom->importNode($node, true);
    $dom->appendChild($n);
    $xpath = new DomXpath($dom);
    $temp = $xpath->query('./Property[@name="NameLower"]', $product);
    if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have a NameLower property\n");
    $name = $temp->item(0)->attributes->getNamedItem('value')->value;

    $temp = $xpath->query('./Property[@name="Subtitle"]/Property[@name="Value"]', $product);
    if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have a Subtitle property\n");
    $subtitle = $temp->item(0)->attributes->getNamedItem('value')->value;

    $temp = $xpath->query('./Property[@name="Icon"]/Property[@name="Filename"]', $product);
    if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have an Icon property\n");
    $icon = $temp->item(0)->attributes->getNamedItem('value')->value;

    $temp = $xpath->query('./Property[@name="Type"]/Property[@name="ProductCategory"]', $product);
    if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have a Type property\n");
    $type = $temp->item(0)->attributes->getNamedItem('value')->value;

    if(strstr($icon, 'TEXTURES/UI/FRONTEND/ICONS/COOKINGPRODUCTS') === FALSE) continue;
    $stringsRaw[$name] = [];

    $itemsTransient[$name]['subtitle'] = $subtitle;
    $stringsRaw[$subtitle] = [];

    $itemsTransient[$name]['icon'] = $icon;
    $itemsTransient[$name]['type'] = $type;

    if(DEBUG >= 2) echo "$name ($subtitle) -> $icon\n";
    if(DEBUG) break;
}
echo "Loaded ".count($itemsTransient)." items and ".count($stringsRaw)." strings\n";

##### Searching for translations #####

$reader = new XMLReader();
foreach($translationFiles as $translationFile) {
    $reader->open($translationFile);
    while($reader->read()) {
        if($reader->nodeType !== XMLREADER::ELEMENT
          || $reader->localName !== 'Property'
          || $reader->getAttribute('value') !== 'TkLocalisationEntry.xml') continue;

        $node = $reader->expand();
        $dom = new DomDocument();
        $dom->formatOutput = true;
        $n = $dom->importNode($node, true);
        $dom->appendChild($n);
        $xpath = new DomXpath($dom);
        foreach($stringsRaw as $key => $value) {
            $results = $xpath->query('./Property[@name="Id" and @value="'.$key.'"]/../Property[@name="English"]/Property[@name="Value"]', $context);
            if($results === FALSE) die("Error: Name search for $key in $translationFile failed\n");
            if($results->length > 1) die("Error: Too many results searching name for $key in $translationFile\n");
            if($results->length <= 0) continue;

            $translation = $results->item(0)->attributes->getNamedItem('value')->value;
            $strings[$key]['translation'] = $translation;
            $strings[$key]['file'] = $translationFile;
            unset($stringsRaw[$key]);
            if(DEBUG >= 2) echo "Translation: $key -> $translation\n";
        }
    }
}

##### Combining items & translated strings #####

$items = [];

foreach($itemsTransient as $key => $item) {
    if(!isset($strings[$key]) || !isset($strings[$item['subtitle']])) {
        // Untranslated string(s)
        continue;
    }

    $item['subtitle'] = $strings[$item['subtitle']]['translation'];
    $items[$strings[$key]['translation']] = $item;
}

##### Output #####

#uasort($items,
#  function($a, $b) {
#    return strcmp($a['name'], $b['name']);
#  }
#);
ksort($items);

#$temp = [];
#foreach($items as $item) {
#    $temp[$item['name']] = $item['icon'];
#}
#if(($json = json_encode($temp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== FALSE) {
if(($json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== FALSE) {
    file_put_contents('items.json', $json, LOCK_EX);
}

if(DEBUG) {
    var_dump($itemsTransient);
    var_dump($items);
}

if($stringsRaw) {
    $list = '';
    foreach($stringsRaw as $key => $value) {
        $list .= $list ? ', '.$key : $key;
    }
    echo "No translation could be found for the following items: $list\n";
}
?>
