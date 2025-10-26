from flask import Blueprint, request, jsonify, current_app
from typing import Any, Dict, List
from storage.wifi_store import get_entry, put_entry

wifi_bp = Blueprint("wifi", __name__, url_prefix="/api/wifi")

def _require_api_key():
    """Protección opcional por API key (header X-API-Key). Si API_KEY no está configurado, no aplica."""
    need = current_app.config.get("API_KEY")
    if not need:
        return None
    got = request.headers.get("X-API-Key")
    if got != need:
        return jsonify({"error": "unauthorized"}), 401
    return None

def _normalize_networks(nets: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    cleaned = []
    for n in nets:
        ssid = (n or {}).get("ssid", "").strip()
        if not ssid:
            continue
        password = str((n or {}).get("password", ""))
        try:
            priority = int((n or {}).get("priority", 0))
        except Exception:
            priority = 0
        enabled = bool((n or {}).get("enabled", True))
        cleaned.append({
            "ssid": ssid,
            "password": password,
            "priority": priority,
            "enabled": enabled
        })
    return cleaned

@wifi_bp.post("/put")
def wifi_put():
    """
    Body JSON:
    {
      "CBI": "16550441187",
      "networks": [
        {"ssid":"NUEVA_RED","password":"clave","priority":120,"enabled":true},
        {"ssid":"RESCATE","password":"backup","priority":90,"enabled":true}
      ],
      "replace": true   # opcional, default true | si false, hace merge por SSID
    }
    - Si replace=true, debes ENVIAR TODAS las redes que quieras conservar (primaria y respaldo).
    - Si replace=false, puedes enviar sólo las que agregas o modificas; las demás se conservan.
    """
    ak_err = _require_api_key()
    if ak_err:
        return ak_err

    try:
        body = request.get_json(force=True, silent=False)
    except Exception:
        return jsonify({"error": "JSON inválido"}), 400

    cbi = (body or {}).get("CBI")
    nets = (body or {}).get("networks", [])
    replace = bool((body or {}).get("replace", True))

    if not cbi or not isinstance(nets, list) or len(nets) == 0:
        return jsonify({"error": "CBI o networks inválidos"}), 400

    cleaned = _normalize_networks(nets)
    if not cleaned:
        return jsonify({"error": "Lista de networks vacía tras limpieza"}), 400

    entry = put_entry(cbi, cleaned, current_app.config["WIFI_STORE_PATH"], replace=replace)
   
    return jsonify({"status": "ok", "version": entry["version"]}), 200

@wifi_bp.get("/get")
def wifi_get():
    """
    GET /api/wifi/get?cbi=16550441187
    Retorna la versión y las redes vigentes para ese CBI.
    """
    ak_err = _require_api_key()
    if ak_err:
        return ak_err

    cbi = request.args.get("cbi", "").strip()
    if not cbi:
        return jsonify({"error": "cbi requerido"}), 400

    entry = get_entry(cbi, current_app.config["WIFI_STORE_PATH"])
    if not entry:
        return jsonify({"version": 0, "networks": []}), 200

    return jsonify(entry), 200