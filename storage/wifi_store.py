import json
import os
from threading import Lock
from datetime import datetime
from typing import Dict, Any, Optional

_store_lock = Lock()
_wifi_store: Dict[str, Dict[str, Any]] = {}
_store_loaded = False
_store_path = "wifi_store.json"

def _ensure_loaded(path: str):
    global _store_loaded, _store_path
    if _store_loaded:
        return
    _store_path = path or _store_path
    if os.path.exists(_store_path):
        try:
            with open(_store_path, "r", encoding="utf-8") as f:
                data = json.load(f)
                if isinstance(data, dict):
                    _wifi_store.update(data)
        except Exception:
            # Si el archivo está corrupto, comenzamos vacío
            pass
    _store_loaded = True

def _save() -> bool:
    try:
        with open(_store_path, "w", encoding="utf-8") as f:
            json.dump(_wifi_store, f, ensure_ascii=False, indent=2)
        return True
    except Exception:
        return False

def get_entry(cbi: str, store_path: str) -> Optional[Dict[str, Any]]:
    with _store_lock:
        _ensure_loaded(store_path)
        return _wifi_store.get(cbi)

def put_entry(cbi: str, networks: list, store_path: str, replace: bool = True) -> Dict[str, Any]:
    """
    networks: lista normalizada de dicts: {ssid:str, password:str, priority:int, enabled:bool}
    replace=True: reemplaza completamente la lista. False: merge por SSID.
    Retorna el entry final con version actualizada.
    """
    with _store_lock:
        _ensure_loaded(store_path)
        entry = _wifi_store.get(cbi, {"version": 0, "networks": [], "updated_at": ""})

        if replace:
            entry["networks"] = networks
        else:
            # Merge por ssid: lo nuevo pisa a lo viejo
            merged = {n.get("ssid"): n for n in entry.get("networks", []) if n.get("ssid")}
            for n in networks:
                merged[n.get("ssid")] = n
            entry["networks"] = list(merged.values())

        entry["version"] = int(entry.get("version", 0)) + 1
        entry["updated_at"] = datetime.utcnow().isoformat()
        _wifi_store[cbi] = entry
        _save()
        return entry

def get_all(store_path: str) -> Dict[str, Dict[str, Any]]:
    with _store_lock:
        _ensure_loaded(store_path)
        return dict(_wifi_store)