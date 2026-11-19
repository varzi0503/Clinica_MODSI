# Empacota a Clínica MODSI para o AVE (PHP 8.3, ~/public_html).
# Correr na pasta do projeto, no main atualizado: .\deploy\ave\empacotar.ps1
# Gera $HOME\clinica-ave.tar.gz (contém o .env com a palavra-passe: apagar depois de instalar) com:
#   clinica/      a aplicação (fica em ~/clinica, fora da área pública)
#   public_html/  o que o Apache serve (fica em ~/public_html)
& {
    $ErrorActionPreference = 'Stop'
    if (-not (Test-Path artisan)) { throw "Corre isto na pasta do projeto. Parei." }
    if (-not (Select-String -Path composer.json -Pattern '"php": "8.3.27"' -Quiet)) { throw "Este main ainda não tem o PR do PHP 8.3. Parei." }
    if (git status --porcelain) { throw "Há alterações por gravar no git. Parei." }

    $pacote = "$HOME\clinica-ave"
    if (Test-Path $pacote) { Remove-Item $pacote -Recurse -Force }
    New-Item -ItemType Directory "$pacote\clinica", "$pacote\public_html" | Out-Null

    git archive --format=zip -o "$pacote\fonte.zip" HEAD
    if ($LASTEXITCODE) { throw "git archive falhou. Parei." }
    tar -xf "$pacote\fonte.zip" -C "$pacote\clinica"
    Remove-Item "$pacote\fonte.zip"

    npm run build
    if ($LASTEXITCODE) { throw "npm run build falhou. Parei." }
    Copy-Item public\build "$pacote\clinica\public\build" -Recurse

    Push-Location "$pacote\clinica"
    try {
        composer install --no-dev --optimize-autoloader --no-interaction
        if ($LASTEXITCODE) { throw "composer install falhou. Parei." }
        $chave = php artisan key:generate --show
        if ($LASTEXITCODE) { throw "Não consegui gerar a APP_KEY. Parei." }
    } finally { Pop-Location }
    Remove-Item "$pacote\clinica\tests", "$pacote\clinica\.github", "$pacote\clinica\.ai" -Recurse -Force -ErrorAction SilentlyContinue

    $segura = Read-Host "Palavra-passe do MariaDB do AVE (a do phpMyAdmin)" -AsSecureString
    $pw = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($segura))
    $segura2 = Read-Host "Repete a palavra-passe" -AsSecureString
    $pw2 = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($segura2))
    if ($pw -cne $pw2) { throw "As palavras-passe não coincidem. Parei." }
    if ($pw.Contains("'")) { throw "A palavra-passe tem uma plica ('), que o .env não aceita entre plicas. Parei." }
    $pwEnv = $pw.Replace('$', '$$')
    $conteudo = (Get-Content .env.example -Raw) `
        -replace '(?m)^APP_ENV=.*', 'APP_ENV=production' `
        -replace '(?m)^APP_KEY=.*', "APP_KEY=$chave" `
        -replace '(?m)^APP_DEBUG=.*', 'APP_DEBUG=false' `
        -replace '(?m)^APP_URL=.*', 'APP_URL=https://ave.dee.isep.ipp.pt/~1212100' `
        -replace '(?m)^LOG_LEVEL=.*', 'LOG_LEVEL=error' `
        -replace '(?m)^DB_HOST=.*', 'DB_HOST=localhost' `
        -replace '(?m)^DB_DATABASE=.*', 'DB_DATABASE=1212100' `
        -replace '(?m)^DB_USERNAME=.*', 'DB_USERNAME=1212100' `
        -replace '(?m)^DB_PASSWORD=.*', "DB_PASSWORD='$pwEnv'" `
        -replace '(?m)^SESSION_PATH=.*', 'SESSION_PATH=/~1212100'
    [IO.File]::WriteAllText("$pacote\clinica\.env", $conteudo, (New-Object Text.UTF8Encoding $false))

    Copy-Item "$pacote\clinica\public\*" "$pacote\public_html" -Recurse -Force
    # Ficheiros próprios do AVE: index.php aponta para ~/clinica, .htaccess sem "Options", .user.ini e verifica.php.
    Copy-Item deploy\ave\public_html\* "$pacote\public_html" -Force
    tar -czf "$HOME\clinica-ave.tar.gz" -C $pacote clinica public_html
    if ($LASTEXITCODE) { throw "tar falhou. Parei." }
    Write-Host "Pacote pronto: $HOME\clinica-ave.tar.gz"
}
