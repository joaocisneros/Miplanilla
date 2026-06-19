param(
    [Parameter(Mandatory = $true)][string]$InputPath,
    [Parameter(Mandatory = $true)][string]$OutputPath
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

function Read-ZipText($Zip, [string]$EntryName) {
    $entry = $Zip.GetEntry($EntryName)
    if ($null -eq $entry) { return $null }
    $reader = [System.IO.StreamReader]::new($entry.Open())
    try { return $reader.ReadToEnd() } finally { $reader.Dispose() }
}

function Column-To-Number([string]$Address) {
    $letters = [regex]::Match($Address, '^[A-Z]+').Value
    $n = 0
    foreach ($ch in $letters.ToCharArray()) { $n = ($n * 26) + ([int][char]$ch - 64) }
    return $n
}

$zip = [System.IO.Compression.ZipFile]::OpenRead($InputPath)
try {
    $shared = @()
    $sharedText = Read-ZipText $zip 'xl/sharedStrings.xml'
    if ($sharedText) {
        [xml]$sharedXml = $sharedText
        foreach ($si in $sharedXml.SelectNodes("//*[local-name()='si']")) {
            $parts = @($si.SelectNodes(".//*[local-name()='t']") | ForEach-Object { $_.InnerText })
            $shared += ($parts -join '')
        }
    }

    [xml]$workbookXml = Read-ZipText $zip 'xl/workbook.xml'
    [xml]$relsXml = Read-ZipText $zip 'xl/_rels/workbook.xml.rels'
    $rels = @{}
    foreach ($rel in $relsXml.SelectNodes("//*[local-name()='Relationship']")) {
        $rels[[string]$rel.Id] = [string]$rel.Target
    }

    $result = [ordered]@{
        file = $InputPath
        size = (Get-Item -LiteralPath $InputPath).Length
        date1904 = [bool]([string]$workbookXml.workbook.workbookPr.date1904 -eq '1')
        definedNames = @()
        externalLinks = @()
        sheets = @()
    }

    foreach ($dn in $workbookXml.SelectNodes("//*[local-name()='definedName']")) {
        $result.definedNames += [ordered]@{ name = [string]$dn.name; value = [string]$dn.InnerText }
    }
    foreach ($entry in $zip.Entries | Where-Object { $_.FullName -like 'xl/externalLinks/*.xml' }) {
        $result.externalLinks += $entry.FullName
    }

    foreach ($sheet in $workbookXml.SelectNodes("//*[local-name()='sheet']")) {
        $rid = [string]$sheet.GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
        $target = $rels[$rid] -replace '^/', ''
        if ($target -notlike 'xl/*') { $target = 'xl/' + $target.TrimStart('/') }
        $target = $target.Replace('../', '')
        [xml]$sheetXml = Read-ZipText $zip $target
        $dimension = [string]($sheetXml.SelectSingleNode("//*[local-name()='dimension']").ref)
        $cells = @()
        $formulaCount = 0
        $maxRow = 0
        $maxCol = 0

        foreach ($cell in $sheetXml.SelectNodes("//*[local-name()='c']")) {
            $address = [string]$cell.r
            $row = [int]([regex]::Match($address, '\d+$').Value)
            $col = Column-To-Number $address
            if ($row -gt $maxRow) { $maxRow = $row }
            if ($col -gt $maxCol) { $maxCol = $col }
            $type = [string]$cell.t
            $style = if ($cell.s -ne $null) { [int]$cell.s } else { 0 }
            $formulaNode = $cell.SelectSingleNode("./*[local-name()='f']")
            $valueNode = $cell.SelectSingleNode("./*[local-name()='v']")
            $inlineNode = $cell.SelectSingleNode("./*[local-name()='is']")
            $formula = if ($null -ne $formulaNode) { [string]$formulaNode.InnerText } else { $null }
            if ($formula) { $formulaCount++ }
            $raw = if ($null -ne $valueNode) { [string]$valueNode.InnerText } else { $null }
            $value = $raw
            if ($type -eq 's' -and $raw -match '^\d+$') { $value = $shared[[int]$raw] }
            elseif ($type -eq 'inlineStr' -and $null -ne $inlineNode) {
                $value = (@($inlineNode.SelectNodes(".//*[local-name()='t']") | ForEach-Object { $_.InnerText }) -join '')
            }
            elseif ($type -eq 'b') { $value = ($raw -eq '1') }
            elseif (($type -eq '' -or $type -eq 'n') -and $raw -match '^-?\d+(\.\d+)?([Ee][+-]?\d+)?$') { $value = [double]::Parse($raw, [Globalization.CultureInfo]::InvariantCulture) }

            $cells += [ordered]@{
                address = $address
                row = $row
                col = $col
                type = $type
                style = $style
                value = $value
                raw = $raw
                formula = $formula
            }
        }

        $merges = @($sheetXml.SelectNodes("//*[local-name()='mergeCell']") | ForEach-Object { [string]$_.ref })
        $result.sheets += [ordered]@{
            name = [string]$sheet.name
            state = [string]$sheet.state
            path = $target
            dimension = $dimension
            maxRow = $maxRow
            maxCol = $maxCol
            formulaCount = $formulaCount
            merges = $merges
            cells = $cells
        }
    }

    $result | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $OutputPath -Encoding UTF8
}
finally {
    $zip.Dispose()
}
