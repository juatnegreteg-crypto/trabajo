from flask import Blueprint, request, jsonify, current_app, render_template
import json
from collections import deque
from datetime import datetime
from storage.wifi_store import get_entry

lecturas_bp = Blueprint("lecturas", __name__, url_prefix="/api")

# Buffer circular de las últimas 20 lecturas (en memoria)
_lecturas = deque(maxlen=20)

@lecturas_bp.post("/lecturas")
def recibir_lecturas():
    """
    Espera form-data con key 'body' que incluye el JSON enviado por el dispositivo.
    Devuelve {"status":"ok"} y, si aplica, "wifi_update" con las redes más nuevas para ese CBI:
    {
      "status": "ok",
      "wifi_update": {
        "version": <int>,
        "networks": [ {ssid,password,priority,enabled}, ... ]
      }
    }
    """
    data = request.form.get("body")
    if not data:
        return jsonify({"error": "No hay datos"}), 400

    try:
        lectura_json = json.loads(data)
    except json.JSONDecodeError:
        return jsonify({"error": "JSON inválido"}), 400

    # Extrae datos clave sólo para visualización local (buffer).
    lectura = {
        "hora": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "CBI": lectura_json.get("CBI", "N/A"),
        "estado": lectura_json.get("estado", "N/A"),
        "L1_voltaje": lectura_json.get("unidad_exterior", {}).get("voltaje", 0),
        "L1_corriente": lectura_json.get("unidad_exterior", {}).get("corriente", 0),
        "L1_potencia": lectura_json.get("unidad_exterior", {}).get("potencia", 0),
        "L2_voltaje": lectura_json.get("unidad_interior", {}).get("voltaje", 0),
        "L2_corriente": lectura_json.get("unidad_interior", {}).get("corriente", 0),
        "L2_potencia": lectura_json.get("unidad_interior", {}).get("potencia", 0),
        "temp_ds18b20": lectura_json.get("unidad_interior", {}).get("temperatura_ds18b20", 0),
        "temp_amb": lectura_json.get("ambiente", {}).get("temperatura", 0),
        "humedad": lectura_json.get("ambiente", {}).get("humedad", 0),
        "alerta": lectura_json.get("alerta", "-"),
        "SSID": lectura_json.get("Conexion", {}).get("SSID", "N/A"),
        "IP": lectura_json.get("Conexion", {}).get("IP", "N/A"),
        "MAC": lectura_json.get("Conexion", {}).get("MAC", "N/A")
    }
    _lecturas.append(lectura)

    resp = {"status": "ok"}

    # Lógica de actualización WiFi:
    # El dispositivo puede enviar doc["wifi_config_version"] en el payload.
    cbi = lectura_json.get("CBI")
    device_ver = lectura_json.get("wifi_config_version", 0)

    if cbi:
        entry = get_entry(cbi, current_app.config["WIFI_STORE_PATH"])
        if entry:
            server_ver = int(entry.get("version", 0))
            if server_ver > int(device_ver or 0):
                # Enviamos la actualización
                resp["wifi_update"] = {
                    "version": server_ver,
                    "networks": entry.get("networks", [])
                }

    return jsonify(resp), 200


@lecturas_bp.get("/lecturas")
def ver_lecturas():
    """
    Devuelve las últimas lecturas almacenadas (máximo 20)
    """
    
    return render_template("VerTodo.html", lecturas=list(_lecturas))
