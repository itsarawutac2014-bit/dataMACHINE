<?php
/**
 * Google Sheets Configuration
 * 
 * ตั้งค่าการเชื่อมต่อ Google Sheets ผ่าน Google Apps Script Web App (Webhook)
 * วิธีการใช้งาน:
 * 1. เปิด Google Sheets -> Extensions (ส่วนขยาย) -> Apps Script
 * 2. นำโค้ดจากไฟล์ google_sheet_app_script.js ไปวาง
 * 3. กด Deploy (ทำให้ใช้งานได้) -> New deployment -> Web app
 * 4. ตั้งค่า Who has access (ผู้มีสิทธิ์เข้าถึง) เป็น "Anyone" (ทุกคน)
 * 5. นำ URL ที่ได้ (ขึ้นต้นด้วย https://script.google.com/macros/s/.../exec) มาวางด้านล่างนี้
 */

// ใส่ Web App URL ที่ได้จาก Google Apps Script (หากเว้นว่างไว้ ระบบจะยังไม่ส่งไป Google Sheets)
define('GOOGLE_SHEET_WEBHOOK_URL', '');

// ตั้งค่าว่าต้องการให้ระบบส่งข้อมูลไป Google Sheets อัตโนมัติเมื่อกดบันทึก/อัปโหลดหรือไม่
define('GOOGLE_SHEET_AUTO_SYNC', true);
