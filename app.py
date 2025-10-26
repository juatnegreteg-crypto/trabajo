import os
from flask import Flask
from routes.lecturas import lecturas_bp
from routes.wifi import wifi_bp
from routes.health import health_bp
from routes.ui_wifi import ui_wifi_bp

def create_app():
    app = Flask(__name__)

    # Config opcional: ruta del archivo JSON donde se guardan credenciales por CBI
    app.config["WIFI_STORE_PATH"] = os.environ.get("WIFI_STORE_PATH", "wifi_store.json")

    # Opcional: API key para proteger /api/wifi/*
    app.config["API_KEY"] = os.environ.get("API_KEY", None)

    # Registro de blueprints (endpoints)
    app.register_blueprint(lecturas_bp)
    app.register_blueprint(wifi_bp)
    app.register_blueprint(health_bp)

    app.register_blueprint(ui_wifi_bp)

    return app



if __name__ == "__main__":
    app = create_app()
    # Producción: usar gunicorn/uvicorn y HTTPS si expones Internet
    app.run(host="0.0.0.0", port=int(os.environ.get("PORT", "5000")), debug=True)

    Serial.printf("✅ Conectado a %s | MAC: %s | IP: %s\n",WiFi.SSID().c_str(),WiFi.macAddress().c_str(),WiFi.localIP().toString().c_str());