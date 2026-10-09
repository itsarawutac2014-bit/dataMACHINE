/**
 * Google Apps Script สำหรับรับข้อมูลจากระบบ dataMACHINE บันทึกลง Google Sheets
 * 
 * วิธีติดตั้ง:
 * 1. สร้าง Google Sheet ใหม่ (หรือใช้ไฟล์ที่มีอยู่)
 * 2. ไปที่เมนู "ส่วนขยาย" (Extensions) -> "Apps Script"
 * 3. ลบโค้ดเดิมออกทั้งหมด แล้ววางโค้ดนี้ลงไป
 * 4. กดปุ่มบันทึก (รูปแผ่นดิสก์)
 * 5. กดปุ่ม "ทำให้ใช้งานได้" (Deploy) -> "การทดสอบใช้งานใหม่" (New deployment)
 * 6. เลือกประเภทเป็น "เว็บแอป" (Web app)
 * 7. ตั้งค่า:
 *    - Execute as (ดำเนินการในฐานะ): ฉัน (Me)
 *    - Who has access (ผู้มีสิทธิ์เข้าถึง): ทุกคน (Anyone)  <--- สำคัญมาก!
 * 8. กด "ทำให้ใช้งานได้" (Deploy) แล้วคัดลอก "URL เว็บแอป"
 * 9. นำ URL ไปวางที่ไฟล์ config/google_sheet.php ในระบบ dataMACHINE
 */

function doPost(e) {
  var lock = LockService.getScriptLock();
  lock.tryLock(10000); // รอคิวไม่เกิน 10 วินาทีเพื่อป้องกันบันทึกชนกัน

  try {
    var sheet = getOrCreateSheet();
    var data = {};

    if (e && e.postData && e.postData.contents) {
      try {
        data = JSON.parse(e.postData.contents);
      } catch (err) {
        data = e.parameter || {};
      }
    } else if (e && e.parameter) {
      data = e.parameter;
    }

    if (!data.report_date) {
      return jsonResponse({ success: false, message: 'ไม่มีข้อมูล report_date' });
    }

    var rowValues = [
      data.report_date || '',                      // วันที่
      data.shift || '',                            // กะ
      data.machine_name || 'BHS',                  // เครื่องจักร
      data.shift_start_time || '',                 // เวลาเริ่ม
      data.shift_end_time || '',                   // เวลาจบ
      data.actual_shift_duration || 0,             // เวลาเดินทั้งหมด (นาที)
      data.total_meters || 0,                      // เมตรที่ผลิต
      data.total_weight_ton || 0,                  // น้ำหนัก (ตัน)
      data.total_orders || 0,                      // จำนวนออเดอร์
      data.avg_speed || 0,                         // ความเร็วเฉลี่ย (ม./นาที)
      data.running_time_min || 0,                  // เวลาเดินงานจริง (นาที)
      data.total_loss_minutes || 0,                // เวลาสูญเสียรวม (นาที)
      data.loss_total_pct || 0,                    // % สูญเสีย
      data.total_kpi_score || 0,                   // คะแนน KPI รวม
      data.summary_notes || '',                    // สรุปผลการเดินงาน
      data.remarks || '',                          // หมายเหตุ
      data.raw_filename || '',                     // ไฟล์ที่นำเข้า
      new Date().toLocaleString('th-TH', { timeZone: 'Asia/Bangkok' }) // วันเวลาที่ซิงค์
    ];

    // ตรวจสอบว่ามีข้อมูล วันที่ + กะ + เครื่องจักร นี้อยู่แล้วหรือไม่ (เพื่ออัปเดตแทนการเพิ่มซ้ำ)
    var lastRow = sheet.getLastRow();
    var existingRow = -1;

    if (lastRow > 1) {
      var range = sheet.getRange(2, 1, lastRow - 1, 3).getValues();
      for (var i = 0; i < range.length; i++) {
        var rowDate = formatDateString(range[i][0]);
        var targetDate = formatDateString(data.report_date);
        var rowShift = String(range[i][1]).trim().toUpperCase();
        var targetShift = String(data.shift).trim().toUpperCase();
        var rowMachine = String(range[i][2]).trim().toUpperCase();
        var targetMachine = String(data.machine_name || 'BHS').trim().toUpperCase();

        if (rowDate === targetDate && rowShift === targetShift && rowMachine === targetMachine) {
          existingRow = i + 2; // +2 เพราะเริ่มจาก row 2
          break;
        }
      }
    }

    var action = 'appended';
    if (existingRow > 0) {
      sheet.getRange(existingRow, 1, 1, rowValues.length).setValues([rowValues]);
      action = 'updated';
    } else {
      sheet.appendRow(rowValues);
      existingRow = sheet.getLastRow();
      action = 'appended';
    }

    // จัดรูปแบบตาราง
    formatDataRow(sheet, existingRow);

    return jsonResponse({
      success: true,
      action: action,
      row: existingRow,
      message: (action === 'updated' ? 'อัปเดตข้อมูลแถวที่ ' : 'เพิ่มข้อมูลใหม่ในแถวที่ ') + existingRow + ' เรียบร้อย'
    });

  } catch (err) {
    return jsonResponse({ success: false, error: err.toString() });
  } finally {
    lock.releaseLock();
  }
}

function doGet(e) {
  return jsonResponse({ status: 'online', service: 'dataMACHINE Google Sheets Webhook Active' });
}

function getOrCreateSheet() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheetName = 'รายงานประจำกะ';
  var sheet = ss.getSheetByName(sheetName);

  if (!sheet) {
    sheet = ss.insertSheet(sheetName);
  }

  // สร้างหัวตารางถ้ายังไม่มี
  if (sheet.getLastRow() === 0) {
    var headers = [
      'วันที่', 'กะ', 'เครื่องจักร', 'เวลาเริ่ม', 'เวลาจบ', 'เวลาเดินกะ (นาที)',
      'เมตรที่ผลิต (m)', 'น้ำหนัก (ตัน)', 'จำนวนออเดอร์', 'ความเร็วเฉลี่ย (m/min)',
      'เวลาเดินงานจริง (นาที)', 'เวลาสูญเสีย (นาที)', '% สูญเสีย', 'คะแนน KPI',
      'สรุปผลการเดินงาน', 'หมายเหตุ', 'ชื่อไฟล์ต้นทาง', 'วันเวลาที่บันทึก'
    ];
    sheet.appendRow(headers);

    var headerRange = sheet.getRange(1, 1, 1, headers.length);
    headerRange.setBackground('#f59e0b');
    headerRange.setFontColor('#ffffff');
    headerRange.setFontWeight('bold');
    headerRange.setHorizontalAlignment('center');
    sheet.setFrozenRows(1);

    for (var col = 1; col <= headers.length; col++) {
      sheet.autoResizeColumn(col);
    }
  }

  return sheet;
}

function formatDataRow(sheet, row) {
  try {
    sheet.getRange(row, 1, 1, 5).setHorizontalAlignment('center');
    sheet.getRange(row, 6, 1, 8).setHorizontalAlignment('right');
    sheet.getRange(row, 13).setNumberFormat('0.00"%"');
    sheet.getRange(row, 7).setNumberFormat('#,##0');
    sheet.getRange(row, 8).setNumberFormat('#,##0.00');
    sheet.getRange(row, 14).setHorizontalAlignment('center');
  } catch (e) {}
}

function formatDateString(val) {
  if (!val) return '';
  if (val instanceof Date) {
    var y = val.getFullYear();
    var m = ('0' + (val.getMonth() + 1)).slice(-2);
    var d = ('0' + val.getDate()).slice(-2);
    return y + '-' + m + '-' + d;
  }
  return String(val).trim().substring(0, 10);
}

function jsonResponse(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
