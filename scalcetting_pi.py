import time
import requests
import json
import threading
from gpiozero import Button

# ==========================================
# CONFIGURAZIONE
# ==========================================

# IMPOSTAZIONI DI CONNESSIONE
API_ENDPOINT = "https://scalcetting.420things.cv/live.php?api"

# Pin GPIO per i Sensori a Barriera Infrarossi dei GOAL
PIN_SENSOR_BLU = 27   # Squadra 1 (Blu) - Swapped from 17
PIN_SENSOR_ROSSO = 17 # Squadra 2 (Rossa) - Swapped from 27

# Pin GPIO per i pulsanti Arcade "-1 Goal"
PIN_BTN_MINUS_BLU = 23   # Swapped from 22
PIN_BTN_MINUS_ROSSO = 22 # Swapped from 23

# Secondi minimi che devono passare tra un gol e l'altro per evitare "rimbalzi" doppi
# Ridotto a 0.01 (10ms) per non perdere le palline veloci
DEBOUNCE_GOAL_SECONDS = 0.01
DEBOUNCE_BTN_SECONDS = 1.0

# ==========================================
# VARIABILI DI STATO
# ==========================================
is_match_full = False

# ==========================================
# FUNZIONI HTTP API
# ==========================================
def send_api_request(action, team):
    payload = {"action": action, "team": team}
    
    def make_request():
        try:
            print(f"--> Inviando {action} Squadra {team}...")
            response = requests.post(API_ENDPOINT, json=payload, timeout=10)
            if response.status_code == 200:
                print(f"    [OK] Trasmesso con successo.")
            else:
                print(f"    [ERRORE] Il server ha risposto con codice {response.status_code}")
        except Exception as e:
            print(f"    [ERRORE CONNESSIONE] {e}")

    threading.Thread(target=make_request).start()

# ==========================================
# CALLBACKS
# ==========================================
def goal_blu():
    if not is_match_full:
        print(" [!] Goal non registrato: Partita non ancora pronta (mancano giocatori)")
        return
    print("\n⚽ RILEVATO GOAL SQUADRA BLU! ⚽")
    send_api_request("add_goal", 1)

def goal_rosso():
    if not is_match_full:
        print(" [!] Goal non registrato: Partita non ancora pronta (mancano giocatori)")
        return
    print("\n⚽ RILEVATO GOAL SQUADRA ROSSA! ⚽")
    send_api_request("add_goal", 2)

def sub_blu():
    if not is_match_full:
        print(" [!] Azione ignorata: Partita non pronta")
        return
    print("\n[-] Disdetta Goal Squadra BLU")
    send_api_request("sub_goal", 1)

def sub_rosso():
    if not is_match_full:
        print(" [!] Azione ignorata: Partita non pronta")
        return
    print("\n[-] Disdetta Goal Squadra ROSSA")
    send_api_request("sub_goal", 2)

# ==========================================
# INIZIALIZZAZIONE SENSORI (GPIOZERO)
# ==========================================
print("Inizializzazione sensori con GPIOZERO...")

# Creiamo gli oggetti Button (che funzionano benissimo per i sensori a barriera)
# pull_up=True è il default di Button, ma lo specifichiamo per chiarezza.
# bounce_time è il debounce gestito direttamente dalla libreria.
sensor_blu = Button(PIN_SENSOR_BLU, pull_up=True, bounce_time=DEBOUNCE_GOAL_SECONDS)
sensor_rosso = Button(PIN_SENSOR_ROSSO, pull_up=True, bounce_time=DEBOUNCE_GOAL_SECONDS)
btn_blu = Button(PIN_BTN_MINUS_BLU, pull_up=True, bounce_time=DEBOUNCE_BTN_SECONDS)
btn_rosso = Button(PIN_BTN_MINUS_ROSSO, pull_up=True, bounce_time=DEBOUNCE_BTN_SECONDS)

# Colleghiamo le funzioni agli eventi
sensor_blu.when_pressed = goal_blu
sensor_rosso.when_pressed = goal_rosso
btn_blu.when_pressed = sub_blu
btn_rosso.when_pressed = sub_rosso

print("\n==================================")
print(" Scalcetting PI - IN ESECUZIONE!")
print(f" Invio API su: {API_ENDPOINT}")
print(" CTRL+C per uscire.")
print("==================================\n")

try:
    while True:
        try:
            # Recuperiamo lo stato della partita dal sito
            res = requests.get(API_ENDPOINT, timeout=10)
            if res.status_code == 200:
                data = res.json()
                # Il server ci dice se la partita è "ready" (tutti e 4 presenti)
                full = data.get('is_match_ready', False)
                
                if full != is_match_full:
                    if full:
                        print("✅ PARTITA PRONTA! I Sensori sono ora ATTIVI.")
                    else:
                        print("⏳ IN ATTESA: Completa la formazione sul sito.")
                    is_match_full = full
        except Exception as e:
            if is_match_full:
                print(f" [!] Errore connessione: {e}")
                is_match_full = False
        time.sleep(3)
except KeyboardInterrupt:
    print("\nChiusura...")
