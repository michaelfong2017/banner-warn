env_name="venv"
python3 -m venv $env_name
$env_name/bin/pip install --upgrade pip
$env_name/bin/pip install wheel
$env_name/bin/pip install black
$env_name/bin/pip install ipykernel
$env_name/bin/pip install typer
$env_name/bin/pip install dependency-injector
$env_name/bin/pip install pytz
$env_name/bin/pip install fastapi
$env_name/bin/pip install "uvicorn[standard]"
$env_name/bin/pip install python-multipart
