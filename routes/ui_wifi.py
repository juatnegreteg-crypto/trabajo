from flask import Blueprint, render_template

ui_wifi_bp = Blueprint("ui_wifi", __name__, url_prefix="/ui")

@ui_wifi_bp.get("/wifi")
def wifi_manager():
    # Renderiza la interfaz visual para gestionar redes WiFi por CBI
    return render_template("wifi_manager.html")
