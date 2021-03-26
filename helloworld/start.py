import subprocess
import platform
import os

## Handle arguments
import sys
sender_address = sys.argv[1]

if os.path.isdir('env'):
    first_time = False
else:
    first_time = True
if first_time:
    subprocess.run(['python3', '-m', 'venv', 'env'])
if platform.system() == 'Linux' or platform.system() == 'Darwin':
    if first_time:
        subprocess.run("env/bin/python3 -m pip install --upgrade pip", shell=True) # subprocess.run / subprocess.call (old) waits this subprocess to return before proceeding
        subprocess.run("env/bin/pip3 install grpcio grpcio-tools", shell=True) # subprocess.run / subprocess.call (old) waits this subprocess to return before proceeding
    subprocess.Popen(f"env/bin/python3 greeter_client.py {sender_address}", shell=True) # subprocess.Popen does not wait this subprocess to return before proceeding
