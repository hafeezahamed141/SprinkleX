#include <Wire.h>
#include <RTClib.h>
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <ArduinoJson.h>
#include <Servo.h>
#include <Adafruit_NeoPixel.h>
#include <NTPClient.h>
#include <WiFiUdp.h>

// -------------------- Pins --------------------
#define SENSOR1 D7 
#define SENSOR2 D0
#define SENSOR3 D5
#define SERVO_PIN D4
#define LED_PIN   D6
#define LED_COUNT 3
#define BUTTON_PIN D3

// -------------------- Wi-Fi --------------------
const char* ssid = "Pixel 7";
const char* password = "nigganigga";

// Server URLs
String fetchURL = "http://192.168.29.44/smart_irrigation_v2/json/current_crop.json";
String logURL   = "http://192.168.29.44/sprinkler_web/log.php";

// -------------------- Objects --------------------
RTC_DS3231 rtc;
Servo waterValve;
Adafruit_NeoPixel strip(LED_COUNT, LED_PIN, NEO_GRB + NEO_KHZ800);
WiFiClient client;

// -------------------- Schedule Structure --------------------
struct Schedule {
  String plant;
  int hour;
  int minute;
  bool runToday;
};

Schedule schedules[10];
int scheduleCount = 0;

// Timer for printing time
unsigned long lastTimePrint = 0;
const unsigned long timePrintInterval = 10000;

// -------------------- NTP Setup --------------------
WiFiUDP ntpUDP;
NTPClient timeClient(ntpUDP, "pool.ntp.org", 19800);

// -------------------- Function Declarations --------------------
bool fetchSchedules();
void blinkLEDs(bool success);
void updateLEDs();
bool anySensorDry();
String getMoistureSensors();
void waterPlant();
void sendLog(String plant, String moisture, String status, String event_type);

// -------------------- Setup --------------------
void setup() { 
  Serial.begin(115200);

  pinMode(SENSOR1, INPUT);
  pinMode(SENSOR2, INPUT);
  pinMode(SENSOR3, INPUT);
  pinMode(BUTTON_PIN, INPUT_PULLUP);

  waterValve.attach(SERVO_PIN);
  waterValve.write(180);

  strip.begin();
  strip.show();

  if(!rtc.begin()) {
    Serial.println("RTC not found!");
    while(1);
  }

  // WiFi Connection
  WiFi.begin(ssid, password);
  Serial.print("Connecting to Wi-Fi");
  while(WiFi.status() != WL_CONNECTED){
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nConnected!");

  // Set RTC via NTP
  timeClient.begin();
  timeClient.update();
  DateTime now(timeClient.getEpochTime());
  rtc.adjust(now);
  Serial.println("RTC synchronized!");

  fetchSchedules();
}

// -------------------- Loop --------------------
void loop() {
  DateTime now = rtc.now();

  if (millis() - lastTimePrint >= timePrintInterval) {
    lastTimePrint = millis();
    Serial.print("Current Time: ");
    Serial.print(now.hour());
    Serial.print(":");
    Serial.print(now.minute());
    Serial.print(":");
    Serial.println(now.second());
  }

  for(int i=0; i<scheduleCount; i++){
    Schedule &s = schedules[i];

    if(now.hour() == s.hour && now.minute() == s.minute && !s.runToday){
      Serial.println("Starting scheduled watering for: " + s.plant);

      String moistureBefore = getMoistureSensors();
      sendLog(s.plant, moistureBefore, "Watering", "pre");

      if(anySensorDry()){
        waterPlant();
      } else {
        sendLog(s.plant, moistureBefore, "Watering", "skipped");
      }

      String moistureAfter = getMoistureSensors();
      sendLog(s.plant, moistureAfter, "Watering", "post");

      s.runToday = true;
    }

    if(now.hour() == 0 && now.minute() == 1){
      s.runToday = false;
    }
  }

  updateLEDs();

  if (digitalRead(BUTTON_PIN) == LOW) {
    Serial.println("Button pressed! Updating schedules...");
    bool success = fetchSchedules();
    blinkLEDs(success);
    delay(2000);
  }

  delay(5000);
}

// -------------------- Functions --------------------
bool fetchSchedules() {
  if(WiFi.status() == WL_CONNECTED){
    HTTPClient http;
    http.begin(client, fetchURL);
    int httpCode = http.GET();

    if(httpCode == 200){
      String payload = http.getString();
      Serial.println("Schedules JSON: " + payload);

      DynamicJsonDocument doc(1024);
      DeserializationError error = deserializeJson(doc, payload);

      if(error){
        Serial.println("JSON Parse Error!");
        http.end();
        return false;
      }

      scheduleCount = doc.size();

      for(int i=0;i<scheduleCount;i++){
        schedules[i].plant = doc[i]["plant"].as<String>();

        String t = doc[i]["daily_times"];
        int comma = t.indexOf(",");
        String h1 = t.substring(0,2);
        String m1 = t.substring(3,5);

        schedules[i].hour = h1.toInt();
        schedules[i].minute = m1.toInt();
        schedules[i].runToday = false;
      }

      http.end();
      return true;
    } 
    http.end();
    return false;
  }
  return false;
}

void blinkLEDs(bool success) {
  uint32_t color = success ? strip.Color(0,255,0) : strip.Color(255,0,0);
  for(int i=0;i<2;i++){
    for(int n=0;n<3;n++) strip.setPixelColor(n, color);
    strip.show();
    delay(250);
    strip.clear();
    strip.show();
    delay(250);
  }
}

bool anySensorDry(){
  return digitalRead(SENSOR1) == HIGH ||
         digitalRead(SENSOR2) == HIGH ||
         digitalRead(SENSOR3) == HIGH;
}

String getMoistureSensors(){
  return String(digitalRead(SENSOR1)) + "," +
         String(digitalRead(SENSOR2)) + "," +
         String(digitalRead(SENSOR3));
}

void waterPlant(){
  waterValve.write(0);
  Serial.println("Watering...");

  while(anySensorDry()){
    Serial.print("Wet sensors: ");
    Serial.println(getMoistureSensors());
    delay(1000);
  }

  waterValve.write(180);
  Serial.println("Watering stopped.");
}

void sendLog(String plant, String moisture, String status, String event_type){
  if(WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = logURL +
    "?plant=" + plant +
    "&moisture=" + moisture +
    "&status=" + status +
    "&event_type=" + event_type;

  Serial.println("Sending log: " + url);
  http.begin(client, url);
  int code = http.GET();

  if(code > 0){
    Serial.println("Response: " + http.getString());
  } else {
    Serial.println("Error sending log.");
  }

  http.end();
}

void updateLEDs(){
  strip.setPixelColor(0, digitalRead(SENSOR1) ? strip.Color(255,0,0) : strip.Color(0,0,255));
  strip.setPixelColor(1, digitalRead(SENSOR2) ? strip.Color(255,0,0) : strip.Color(0,0,255));
  strip.setPixelColor(2, digitalRead(SENSOR3) ? strip.Color(255,0,0) : strip.Color(0,0,255));
  strip.show();
}
