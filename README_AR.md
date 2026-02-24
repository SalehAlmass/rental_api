# Rental PHP API - Fixed

## تركيب
- انسخ مجلد `rental_api` إلى:
  C:\xampp\htdocs\rental_api\

## اختبار سريع
GET:
http://localhost/rental_api/index.php?path=

## Login
POST:
http://localhost/rental_api/index.php?path=auth/login

يدعم:
- JSON
- أو form-data (Thunder Client)

ثم استخدم:
Authorization: Bearer <token>
