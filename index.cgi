#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Xserver CGI エントリーポイント"""
import sys
import os

# Xserverのpipインストール先をパスに追加（ユーザー名に応じて変更）
# sys.path.insert(0, '/home/YOUR_XSERVER_USER/local/lib/python3.x/site-packages')

sys.path.insert(0, os.path.dirname(__file__))

from wsgiref.handlers import CGIHandler
from app import app

# 本番環境ではデバッグOFF
app.debug = False

CGIHandler().run(app)
