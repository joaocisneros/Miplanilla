param(
    [Parameter(Mandatory = $true)][string]$InputPath,
    [Parameter(Mandatory = $true)][string]$OutputPath
)

$ErrorActionPreference = 'Stop'
$excel = $null
$workbook = $null

try {
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false
    $excel.DisplayAlerts = $false
    $excel.AskToUpdateLinks = $false
    $excel.EnableEvents = $false
    $excel.AutomationSecurity = 3

    $workbook = $excel.Workbooks.Open($InputPath, 0, $true)
    $result = [ordered]@{
        file = $InputPath
        calculationVersion = $workbook.CalculationVersion
        hasVBProject = $workbook.HasVBProject
        names = @()
        links = @()
        sheets = @()
    }

    foreach ($name in $workbook.Names) {
        $result.names += [ordered]@{ name = $name.Name; refersTo = $name.RefersTo }
    }

    $links = $workbook.LinkSources(1)
    if ($null -ne $links) {
        foreach ($link in $links) { $result.links += [string]$link }
    }

    foreach ($sheet in $workbook.Worksheets) {
        $used = $sheet.UsedRange
        $rows = [int]$used.Rows.Count
        $cols = [int]$used.Columns.Count
        $startRow = [int]$used.Row
        $startCol = [int]$used.Column
        $cells = @()
        $formulaCount = 0

        for ($r = 1; $r -le $rows; $r++) {
            for ($c = 1; $c -le $cols; $c++) {
                $cell = $used.Cells.Item($r, $c)
                $value = $cell.Value2
                $formula = $cell.Formula
                $text = $cell.Text
                $hasFormula = [bool]$cell.HasFormula
                if ($hasFormula) { $formulaCount++ }
                if ($null -ne $value -or $hasFormula -or -not [string]::IsNullOrWhiteSpace([string]$text)) {
                    $cells += [ordered]@{
                        address = $cell.Address($false, $false)
                        row = $startRow + $r - 1
                        col = $startCol + $c - 1
                        value = $value
                        text = [string]$text
                        formula = if ($hasFormula) { [string]$formula } else { $null }
                        numberFormat = [string]$cell.NumberFormat
                    }
                }
                [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($cell)
            }
        }

        $result.sheets += [ordered]@{
            name = $sheet.Name
            visible = $sheet.Visible
            usedAddress = $used.Address()
            usedRows = $rows
            usedCols = $cols
            formulaCount = $formulaCount
            cells = $cells
        }
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($used)
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($sheet)
    }

    $result | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $OutputPath -Encoding UTF8
}
finally {
    if ($null -ne $workbook) {
        $workbook.Close($false)
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($workbook)
    }
    if ($null -ne $excel) {
        $excel.Quit()
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel)
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
