$files = Get-ChildItem -Path "app/Filament/Resources" -Recurse -Filter "*.php"
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $changed = $false

    # Fix $navigationIcon - any variant to the correct format
    if ($content -match 'protected static \?string \$navigationIcon') {
        $content = $content -replace 'protected static \?string \$navigationIcon', 'protected static string|BackedEnum|null $navigationIcon'
        $changed = $true
    }
    if ($content -match 'protected static \\BackedEnum\|string\|null \$navigationIcon') {
        $content = $content -replace 'protected static \\BackedEnum\|string\|null \$navigationIcon', 'protected static string|BackedEnum|null $navigationIcon'
        $changed = $true
    }

    # Fix $navigationLabel - any variant to the correct format
    if ($content -match 'protected static \?string \$navigationLabel') {
        $content = $content -replace 'protected static \?string \$navigationLabel', 'protected static string|BackedEnum|null $navigationLabel'
        $changed = $true
    }
    if ($content -match 'protected static \\BackedEnum\|string\|null \$navigationLabel') {
        $content = $content -replace 'protected static \\BackedEnum\|string\|null \$navigationLabel', 'protected static string|BackedEnum|null $navigationLabel'
        $changed = $true
    }

    # Fix $navigationGroup - any variant to the correct format
    if ($content -match 'protected static \?string \$navigationGroup') {
        $content = $content -replace 'protected static \?string \$navigationGroup', 'protected static string|UnitEnum|null $navigationGroup'
        $changed = $true
    }
    if ($content -match 'protected static \\UnitEnum\|string\|null \$navigationGroup') {
        $content = $content -replace 'protected static \\UnitEnum\|string\|null \$navigationGroup', 'protected static string|UnitEnum|null $navigationGroup'
        $changed = $true
    }

    if ($changed) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Fixed: $($file.FullName)"
    }
}
