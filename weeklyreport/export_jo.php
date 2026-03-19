<?php
declare(strict_types=1);

// Buffer ALL output so stray warnings don't corrupt the file download
ob_start();

session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";
require_once "csrf_helper.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    header("Location: login.php");
    exit;
}

if (!class_exists('ZipArchive')) {
    ob_end_clean();
    die('
        <div style="font-family:Arial;max-width:560px;margin:80px auto;padding:30px;
                    border:1px solid #f0c040;border-radius:8px;background:#fffbe6;">
            <h2 style="color:#cc7700;">⚠ One Small Setup Step Needed</h2>
            <p>To export with auto-fitted columns, enable ZipArchive in XAMPP:</p>
            <ol style="line-height:2;">
                <li>Open <code>C:\xampp\php\php.ini</code></li>
                <li>Find <code>;extension=zip</code></li>
                <li>Remove the <code>;</code> so it reads <code>extension=zip</code></li>
                <li>Save and <strong>restart Apache</strong> in XAMPP Control Panel</li>
                <li>Try exporting again ✅</li>
            </ol>
            <a href="manage_users.php" style="color:#001f3f;font-weight:bold;">← Back</a>
        </div>
    ');
}

// ── Fetch data ──
$result = $conn->query("
    SELECT full_name, username, email, sex, address,
           unit, sg, position, education, prev_work, created_at
    FROM users WHERE role = 'jo' ORDER BY full_name ASC
");

if (!$result) {
    ob_end_clean();
    die('Database query failed: ' . htmlspecialchars($conn->error));
}

$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;

// ── Column definitions ──
$headers = [
    'No.', 'Full Name', 'Username', 'Email', 'Sex', 'Address',
    'Unit / Div / Ban', 'Salary Grade', 'Position',
    'Education', 'Previous Work', 'Date Created',
];
$COLS = ['A','B','C','D','E','F','G','H','I','J','K','L'];

// ── Auto-calculate column widths ──
$colWidths = array_map('mb_strlen', $headers);

foreach ($rows as $row) {
    $values = [
        '',
        $row['full_name']  ?? '',
        $row['username']   ?? '',
        $row['email']      ?? '',
        $row['sex']        ?? '',
        $row['address']    ?? '',
        $row['unit']       ?? '',
        $row['sg']         ?? '',
        $row['position']   ?? '',
        $row['education']  ?? '',
        $row['prev_work']  ?? '',
        $row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '',
    ];
    foreach ($values as $ci => $val) {
        $len = min(mb_strlen($val), 40);
        if ($len > $colWidths[$ci]) $colWidths[$ci] = $len;
    }
}

foreach ($colWidths as $ci => $w) {
    $colWidths[$ci] = max($w + 2, 8);
}
$colWidths[0] = 5;

// ── Shared strings ──
$ss = []; $ssIdx = [];
function si(string $s): int {
    global $ss, $ssIdx;
    if (!isset($ssIdx[$s])) { $ssIdx[$s] = count($ss); $ss[] = $s; }
    return $ssIdx[$s];
}
function xe(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

foreach ($headers as $h) si($h);
foreach ($rows as $i => $row) {
    si((string)($i + 1));
    si($row['full_name']  ?? '');
    si($row['username']   ?? '');
    si($row['email']      ?? '');
    si($row['sex']        ?? '');
    si($row['address']    ?? '');
    si($row['unit']       ?? '');
    si($row['sg']         ?? '');
    si($row['position']   ?? '');
    si($row['education']  ?? '');
    si($row['prev_work']  ?? '');
    si($row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '');
}

function c(string $ref, string $val, int $style): string {
    $idx = si($val);
    return "<c r=\"{$ref}\" t=\"s\" s=\"{$style}\"><v>{$idx}</v></c>";
}

// ── worksheet.xml ──
$ws  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$ws .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
$ws .= '<sheetViews><sheetView workbookViewId="0">';
$ws .= '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>';
$ws .= '</sheetView></sheetViews>';
$ws .= '<cols>';
foreach ($colWidths as $ci => $w) {
    $n = $ci + 1;
    $ws .= "<col min=\"{$n}\" max=\"{$n}\" width=\"{$w}\" customWidth=\"1\" bestFit=\"1\"/>";
}
$ws .= '</cols>';
$ws .= '<sheetData>';

$ws .= '<row r="1" ht="20" customHeight="1">';
foreach ($headers as $ci => $h) {
    $ws .= c($COLS[$ci].'1', $h, 1);
}
$ws .= '</row>';

foreach ($rows as $ri => $row) {
    $exRow = $ri + 2;
    $style = ($ri % 2 === 0) ? 2 : 3;
    $ws .= "<row r=\"{$exRow}\">";
    $values = [
        (string)($ri + 1),
        $row['full_name']  ?? '',
        $row['username']   ?? '',
        $row['email']      ?? '',
        $row['sex']        ?? '',
        $row['address']    ?? '',
        $row['unit']       ?? '',
        $row['sg']         ?? '',
        $row['position']   ?? '',
        $row['education']  ?? '',
        $row['prev_work']  ?? '',
        $row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '',
    ];
    foreach ($values as $ci => $val) {
        $ws .= c($COLS[$ci].$exRow, $val, $style);
    }
    $ws .= '</row>';
}

$ws .= '</sheetData>';
$ws .= '<pageSetup orientation="landscape" fitToPage="1" fitToWidth="1" fitToHeight="0"/>';
$ws .= '</worksheet>';

// ── sharedStrings.xml ──
$ssCount = count($ss);
$ssXml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$ssXml  .= "<sst xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" count=\"{$ssCount}\" uniqueCount=\"{$ssCount}\">";
foreach ($ss as $str) $ssXml .= '<si><t xml:space="preserve">'.xe($str).'</t></si>';
$ssXml  .= '</sst>';

// ── styles.xml ──
$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Arial"/></font>
    <font><b/><sz val="11"/><name val="Arial"/><color rgb="FFFFFFFF"/></font>
  </fonts>
  <fills count="4">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF001F3F"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF0F4F8"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFCCCCCC"/></left>
      <right style="thin"><color rgb="FFCCCCCC"/></right>
      <top style="thin"><color rgb="FFCCCCCC"/></top>
      <bottom style="thin"><color rgb="FFCCCCCC"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="4">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1">
      <alignment horizontal="center" vertical="center" wrapText="0"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1">
      <alignment vertical="center" wrapText="0"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1">
      <alignment vertical="center" wrapText="0"/>
    </xf>
  </cellXfs>
</styleSheet>';

// ── workbook.xml ──
$wbXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="JO Accounts" sheetId="1" r:id="rId1"/></sheets>
</workbook>';

$ctXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

$relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
</Relationships>';

$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"
    Target="sharedStrings.xml"/>
  <Relationship Id="rId3"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
    Target="styles.xml"/>
</Relationships>';

// ── Pack into xlsx ──
// InfinityFree blocks sys_get_temp_dir(), so write to uploads/ instead
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
$tmpFile = $uploadDir . 'jo_export_' . uniqid() . '.xlsx';

$zip = new ZipArchive();

if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    ob_end_clean();
    die('Failed to create ZIP archive. Check that the uploads/ folder exists and is writable.');
}

$zip->addFromString('[Content_Types].xml',        $ctXml);
$zip->addFromString('_rels/.rels',                $relsXml);
$zip->addFromString('xl/workbook.xml',            $wbXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
$zip->addFromString('xl/worksheets/sheet1.xml',   $ws);
$zip->addFromString('xl/sharedStrings.xml',       $ssXml);
$zip->addFromString('xl/styles.xml',              $stylesXml);
$zip->close();

if (!file_exists($tmpFile) || filesize($tmpFile) === 0) {
    ob_end_clean();
    die('Export failed: file was not created properly.');
}

// ── Discard any stray output, then stream the file ──
ob_end_clean();

$filename = 'JO_Accounts_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($tmpFile);
unlink($tmpFile);
exit;