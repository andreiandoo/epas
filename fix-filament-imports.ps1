$files = Get-ChildItem -Path "app/Filament/Resources" -Recurse -Filter "*.php"
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $changed = $false

    # Check if file uses UnitEnum but doesn't import it
    if ($content -match 'UnitEnum\|string\|null' -and $content -notmatch 'use UnitEnum;') {
        # Add use UnitEnum; after use BackedEnum; or after namespace declaration
        if ($content -match 'use BackedEnum;') {
            $content = $content -replace 'use BackedEnum;', "use BackedEnum;`nuse UnitEnum;"
            $changed = $true
        }
    }

    # Check if file uses BackedEnum but doesn't import it
    if ($content -match 'BackedEnum\|string\|null' -and $content -notmatch 'use BackedEnum;') {
        # Find a good place to add - after the last use statement before the class
        if ($content -match '(use [^;]+;)\r?\n\r?\nclass') {
            $content = $content -replace '(use [^;]+;)(\r?\n\r?\nclass)', "`$1`nuse BackedEnum;`nuse UnitEnum;`$2"
            $changed = $true
        }
    }

    if ($changed) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Fixed imports: $($file.FullName)"
    }
}
