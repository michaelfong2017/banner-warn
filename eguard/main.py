from typing import Optional

import uvicorn
from fastapi import FastAPI, Form
from fastapi.middleware.cors import CORSMiddleware

from util.logger import create_logger

logger = create_logger(debug=True)

app = FastAPI()

origins = ["*"]

app.add_middleware(
    CORSMiddleware,
    allow_origins=origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/")
async def read_root():
    return {"Hello": "World"}

@app.post("/is-known-sender")
async def is_known_sender(sender_address: str = Form(...)):
    logger.info(sender_address)
    return {"is-known-sender": "true"}

@app.post("/set-known-sender")
async def set_known_sender(sender_addresses: str = Form(...)):
    logger.info(sender_addresses)
    return {"set-known-sender": "done"}

@app.post("/set-unknown-sender")
async def set_unknown_sender(sender_addresses: str = Form(...)):
    logger.info(sender_addresses)
    return {"set-unknown-sender": "done"}

if __name__ == '__main__':
    uvicorn.run('main:app', port=8000, host='0.0.0.0', reload=True)
