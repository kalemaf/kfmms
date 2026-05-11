<?PHP
include("config.inc.php");
include("common.inc.php");
include("libraries/sla_helper.php");
// Ensure we only set session save path and start session when no session is active
if (session_status() == PHP_SESSION_NONE) {
  if (!empty($session_save_path)) {
    @session_save_path($session_save_path);
  }
  @session_start();
}

if (empty($_SESSION['group'])) {
  echo "<script type=\"text/javascript\">\nalert(\"You must be logged in to make changes\");\nwindow.opener.location.reload();\n</script>";
  exit;
}

// Helper: send email using PHPMailer SMTP when enabled, fallback to mail()
function send_notification_email($to, $subject, $body, $from = null, $from_name = null, $attachment = null, $attachment_name = null) {
  global $SMTP_ENABLED, $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;

  $from_email = $from ?: ($SMTP_FROM_EMAIL ?? 'no-reply@example.com');
  $from_name = $from_name ?: ($SMTP_FROM_NAME ?? 'Maintenix');

  // Try PHPMailer SMTP if enabled and autoload present
  global $TEMP_DISABLE_SMTP_FOR_TESTS;
  if (!empty($SMTP_ENABLED) && empty($TEMP_DISABLE_SMTP_FOR_TESTS)) {
    $autoload = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (file_exists($autoload)) {
      require_once $autoload;
      try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $SMTP_HOST;
        $mail->Port = !empty($SMTP_PORT) ? (int)$SMTP_PORT : 25;
        if (!empty($SMTP_USER)) {
          $mail->SMTPAuth = true;
          $mail->Username = $SMTP_USER;
          $mail->Password = $SMTP_PASS;
        } else {
          $mail->SMTPAuth = false;
        }
        if (!empty($SMTP_SECURE)) {
          $mail->SMTPSecure = $SMTP_SECURE;
        }
        // Add timeouts to prevent hanging (default 300 is too long)
        $mail->Timeout = 5;  // 5 second timeout for SMTP operations
        $mail->SMTPDebug = 0; // Disable debug output (0=off, 1=error, 2=verbose)
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $body;
        if (!empty($attachment) && !empty($attachment_name)) {
          $mail->addStringAttachment($attachment, $attachment_name);
        }
        $mail->send();
        @file_put_contents(__DIR__ . '/logs/email_send.log', date('c') . " - SMTP send OK to {$to}\n", FILE_APPEND);
        return true;
      } catch (\Exception $e) {
        @file_put_contents(__DIR__ . '/logs/email_send.log', date('c') . " - SMTP send FAILED to {$to}: " . $e->getMessage() . "\n", FILE_APPEND);
        // If PHPMailer fails, fall back to mail()
      }
    }
  }

  // Fallback to PHP mail(); if attachment provided, build a multipart message
  $headers = 'From: ' . $from_email . "\r\n" . 'X-Mailer: PHP/' . phpversion();
  if (empty($attachment) || empty($attachment_name)) {
    return @mail($to, $subject, $body, $headers);
  }

  // build multipart MIME message
  $separator = md5(time());
  $eol = "\r\n";
  $headers .= "MIME-Version: 1.0" . $eol;
  $headers .= "Content-Type: multipart/mixed; boundary=\"" . $separator . "\"" . $eol;

  $message = "--" . $separator . $eol;
  $message .= "Content-Type: text/plain; charset=iso-8859-1" . $eol;
  $message .= "Content-Transfer-Encoding: 7bit" . $eol . $eol;
  $message .= $body . $eol . $eol;

  $message .= "--" . $separator . $eol;
  $message .= "Content-Type: application/pdf; name=\"" . $attachment_name . "\"" . $eol;
  $message .= "Content-Transfer-Encoding: base64" . $eol;
  $message .= "Content-Disposition: attachment; filename=\"" . $attachment_name . "\"" . $eol . $eol;
  $message .= chunk_split(base64_encode($attachment)) . $eol . $eol;
  $message .= "--" . $separator . "--";

  return @mail($to, $subject, $message, $headers);
}

// Simple PDF builder: returns a minimal one-page PDF binary containing the provided lines
function build_simple_pdf($title, $body_lines) {
  $lines = is_array($body_lines) ? $body_lines : explode("\n", wordwrap($body_lines, 80));
  
  // Properly escape text for PDF strings
  $escape = function($text) {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
  };
  
  // Build content stream
  $stream = "BT\n";
  $stream .= "/F1 14 Tf\n";
  $stream .= "50 750 Td\n";
  $stream .= "(" . $escape($title) . ") Tj\n";
  $stream .= "0 -30 Td\n";
  $stream .= "/F1 10 Tf\n";
  
  foreach ($lines as $line) {
    $stream .= "(" . $escape($line) . ") Tj\n";
    $stream .= "0 -14 Td\n";
  }
  $stream .= "ET\n";
  
  // Create object 1: Catalog
  $obj_catalog = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
  
  // Create object 2: Pages
  $obj_pages = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
  
  // Create object 3: Page
  $obj_page = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
  
  // Create object 4: Content stream
  $obj_content = "4 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";
  
  // Create object 5: Font
  $obj_font = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
  
  // Build PDF
  $pdf = "%PDF-1.4\n";
  
  // Track byte offsets for xref
  $offsets = array();
  $offsets[1] = strlen($pdf);
  $pdf .= $obj_catalog;
  
  $offsets[2] = strlen($pdf);
  $pdf .= $obj_pages;
  
  $offsets[3] = strlen($pdf);
  $pdf .= $obj_page;
  
  $offsets[4] = strlen($pdf);
  $pdf .= $obj_content;
  
  $offsets[5] = strlen($pdf);
  $pdf .= $obj_font;
  
  // Build xref
  $xref_offset = strlen($pdf);
  $pdf .= "xref\n";
  $pdf .= "0 6\n";
  $pdf .= "0000000000 65535 f\r\n";
  
  for ($i = 1; $i <= 5; $i++) {
    $pdf .= str_pad($offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n\r\n";
  }
  
  // Build trailer
  $pdf .= "trailer\n";
  $pdf .= "<< /Size 6 /Root 1 0 R >>\n";
  $pdf .= "startxref\n";
  $pdf .= $xref_offset . "\n";
  $pdf .= "%%EOF\n";
  
  return $pdf;
}

/* Set up variables to either operate on a work order or a hot job */
switch($_REQUEST['document'] ?? '')
{
 case 'hot_job':
   if (empty($_REQUEST['hj_id'])) {
       die("ERROR: hot_job document type requires 'hj_id' parameter");
   }
   $table = 'trouble_calls';
   $my = 'hj';
   $id = $_REQUEST['hj_id'];
   $next_link = 'hot_job.php';
   break;

 case 'work_order':
   // For new work orders being created, wo_id might not exist yet - that's OK
   // For updates, wo_id is required
   $wo_id = !empty($_REQUEST['wo_id']) ? $_REQUEST['wo_id'] : null;
   
   $table = 'work_orders';
   $my = 'wo';
   $id = $wo_id;  // Can be null for new records
   $next_link = 'work_order.php';
   break;

 case 'pm_master':
   // Professional PM Master handling
   $pm_id = !empty($_REQUEST['pm_id']) ? (int)$_REQUEST['pm_id'] : 0;
   
   // Build pm_masters row fields
   $pm_title = mysqli_real_escape_string($connection, $_REQUEST['pm_title'] ?? '');
   $asset_id = mysqli_real_escape_string($connection, $_REQUEST['asset_id'] ?? '');
   $description = mysqli_real_escape_string($connection, $_REQUEST['description'] ?? '');
   $maintenance_type = mysqli_real_escape_string($connection, $_REQUEST['maintenance_type'] ?? 'Preventive');
   $status = mysqli_real_escape_string($connection, $_REQUEST['status'] ?? 'Active');
   $frequency_type = mysqli_real_escape_string($connection, $_REQUEST['frequency_type'] ?? 'Time-Based');
   $time_frequency_unit = mysqli_real_escape_string($connection, $_REQUEST['time_frequency_unit'] ?? 'Monthly');
   $time_frequency_value = !empty($_REQUEST['time_frequency_value']) ? (int)$_REQUEST['time_frequency_value'] : 30;
   $grace_period_days = !empty($_REQUEST['grace_period_days']) ? (int)$_REQUEST['grace_period_days'] : 3;
   $meter_type = !empty($_REQUEST['meter_type']) ? mysqli_real_escape_string($connection, $_REQUEST['meter_type']) : NULL;
   $meter_trigger = !empty($_REQUEST['meter_trigger_threshold']) ? (float)$_REQUEST['meter_trigger_threshold'] : NULL;
   $start_date = !empty($_REQUEST['start_date']) ? mysqli_real_escape_string($connection, $_REQUEST['start_date']) : NULL;
   $next_due_date = !empty($_REQUEST['next_due_date']) ? mysqli_real_escape_string($connection, $_REQUEST['next_due_date']) : NULL;
   $planned_labor_hours = !empty($_REQUEST['planned_labor_hours']) ? (float)$_REQUEST['planned_labor_hours'] : 0;
   $required_technician_skill = mysqli_real_escape_string($connection, $_REQUEST['required_technician_skill'] ?? '');
   $estimated_cost = !empty($_REQUEST['estimated_cost']) ? (float)$_REQUEST['estimated_cost'] : 0;
   
   if ($pm_id) {
       // UPDATE existing PM master
       $sql = "UPDATE pm_masters SET pm_title='$pm_title', asset_id='$asset_id', description='$description', maintenance_type='$maintenance_type', status='$status', frequency_type='$frequency_type', time_frequency_unit='$time_frequency_unit', time_frequency_value=$time_frequency_value, grace_period_days=$grace_period_days, meter_type=" . ($meter_type ? "'$meter_type'" : 'NULL') . ", meter_trigger_threshold=" . ($meter_trigger ? $meter_trigger : 'NULL') . ", start_date=" . ($start_date ? "'$start_date'" : 'NULL') . ", next_due_date=" . ($next_due_date ? "'$next_due_date'" : 'NULL') . ", planned_labor_hours=$planned_labor_hours, required_technician_skill='$required_technician_skill', estimated_cost=$estimated_cost, modified_date=NOW() WHERE pm_id=$pm_id";
       $res = mysqli_query($connection, $sql);
       if (!$res) {
           die("Failed to update PM master: " . mysqli_error($connection) . "<br>" . $sql);
       }
       // Delete old tasks/parts for re-insert
       mysqli_query($connection, "DELETE FROM pm_tasks WHERE pm_id=$pm_id");
       mysqli_query($connection, "DELETE FROM pm_required_parts WHERE pm_id=$pm_id");
   } else {
       // INSERT new PM master
       $sql = "INSERT INTO pm_masters (pm_title, asset_id, description, maintenance_type, status, frequency_type, time_frequency_unit, time_frequency_value, grace_period_days, meter_type, meter_trigger_threshold, start_date, next_due_date, planned_labor_hours, required_technician_skill, estimated_cost, created_date) VALUES ('$pm_title', '$asset_id', '$description', '$maintenance_type', '$status', '$frequency_type', '$time_frequency_unit', $time_frequency_value, $grace_period_days, " . ($meter_type ? "'$meter_type'" : 'NULL') . ", " . ($meter_trigger ? $meter_trigger : 'NULL') . ", " . ($start_date ? "'$start_date'" : 'NULL') . ", " . ($next_due_date ? "'$next_due_date'" : 'NULL') . ", $planned_labor_hours, '$required_technician_skill', $estimated_cost, NOW())";
       $res = mysqli_query($connection, $sql);
       if (!$res) {
           die("Failed to insert PM master: " . mysqli_error($connection) . "<br>" . $sql);
       }
       $pm_id = mysqli_insert_id($connection);
   }
   
   // Save tasks
   if (!empty($_REQUEST['task_description']) && is_array($_REQUEST['task_description'])) {
       foreach ($_REQUEST['task_description'] as $i => $desc) {
           $seq = !empty($_REQUEST['task_sequence'][$i]) ? (int)$_REQUEST['task_sequence'][$i] : ($i + 1);
           $est_hours = !empty($_REQUEST['estimated_labor_hours'][$i]) ? (float)$_REQUEST['estimated_labor_hours'][$i] : 0;
           $skill = mysqli_real_escape_string($connection, $_REQUEST['required_skill'][$i] ?? '');
           $tools = mysqli_real_escape_string($connection, $_REQUEST['required_tools'][$i] ?? '');
           $safety = mysqli_real_escape_string($connection, $_REQUEST['safety_instructions'][$i] ?? '');
           $insp_type = mysqli_real_escape_string($connection, $_REQUEST['inspection_type'][$i] ?? 'None');
           $insp_min = !empty($_REQUEST['inspection_min'][$i]) ? (float)$_REQUEST['inspection_min'][$i] : NULL;
           $insp_max = !empty($_REQUEST['inspection_max'][$i]) ? (float)$_REQUEST['inspection_max'][$i] : NULL;
           $insp_unit = mysqli_real_escape_string($connection, $_REQUEST['inspection_unit'][$i] ?? '');
           $desc = mysqli_real_escape_string($connection, $desc);
           
           $sql = "INSERT INTO pm_tasks (pm_id, task_sequence, task_description, estimated_labor_hours, required_skill, required_tools, safety_instructions, inspection_type, inspection_min_value, inspection_max_value, inspection_unit) VALUES ($pm_id, $seq, '$desc', $est_hours, '$skill', '$tools', '$safety', '$insp_type', " . ($insp_min !== NULL ? $insp_min : 'NULL') . ", " . ($insp_max !== NULL ? $insp_max : 'NULL') . ", '$insp_unit')";
           mysqli_query($connection, $sql);
       }
   }
   
   // Save parts
   if (!empty($_REQUEST['part_name']) && is_array($_REQUEST['part_name'])) {
       foreach ($_REQUEST['part_name'] as $i => $pname) {
           $qty = !empty($_REQUEST['quantity'][$i]) ? (int)$_REQUEST['quantity'][$i] : 1;
           $cost = !empty($_REQUEST['unit_cost'][$i]) ? (float)$_REQUEST['unit_cost'][$i] : 0;
           $pname = mysqli_real_escape_string($connection, $pname);
           $total = $qty * $cost;
           
           $sql = "INSERT INTO pm_required_parts (pm_id, part_name, quantity, unit_cost, total_cost) VALUES ($pm_id, '$pname', $qty, $cost, $total)";
           mysqli_query($connection, $sql);
       }
   }
   
   mysqli_close($connection);
   echo "<script type='text/javascript'>
       alert('PM Record Saved');
       window.location.href = 'pm.php';
   </script>
   Close this window (or enable javascript and it will redirect)";
   exit;

 default:
   // Build diagnostic message and notify administrator
   $user = $_SESSION['user'] ?? 'unknown';
   $doc = $_REQUEST['document'] ?? '(none)';
   $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
   $time = date('c');
   $request_snapshot = print_r(
     array(
       'GET' => $_GET,
       'POST' => $_POST,
       'REQUEST' => $_REQUEST,
       'SERVER' => array('REMOTE_ADDR' => $ip, 'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '')
     ),
     true
   );

   $subject = "Maintenix: Save failure (document={$doc}) by {$user}";
   $body = "A problem occurred saving a document in Maintenix.\n\n";
   $body .= "Time: {$time}\nUser: {$user}\nDocument: {$doc}\nIP: {$ip}\n\nRequest Snapshot:\n{$request_snapshot}\n";

  // Attempt to send email (use PHPMailer SMTP when enabled, fallback to mail)
  $from = isset($SMTP_FROM_EMAIL) ? $SMTP_FROM_EMAIL : 'no-reply@example.com';
  send_notification_email($admincontact, $subject, $body, $from);

   // Ensure logs directory exists and append diagnostics
   $logdir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
   if (!is_dir($logdir)) {
     @mkdir($logdir, 0755, true);
   }
   $logfile = $logdir . DIRECTORY_SEPARATOR . 'save_errors.log';
   @file_put_contents($logfile, "[{$time}] {$subject}\n{$body}\n--------------------\n", FILE_APPEND);

   // Show friendly message to the user
   echo "<html><head><title>Error</title></head><body>";
   echo "<h2 style='color:red;'>There was a problem saving the work order.</h2>";
   echo "<p>An administrator has been notified. If the problem persists contact ";
   echo "<a href=\"mailto:{$admincontact}\">{$admincontact}</a>.</p>";
   echo "</body></html>";
   exit;
}

// Safety check: ensure required variables are set before building query
if (empty($table) || empty($my)) {
    $doc_received = $_REQUEST['document'] ?? '(not set)';
    $error_msg = "ERROR: Required document parameters missing. Document type received: '$doc_received'. Table='$table', My='$my'";
    error_log($error_msg);
    
    // Try to send error email to admin
    if (isset($admincontact) && !empty($admincontact)) {
        $subject = "Maintenix: Save.php parameter error";
        $body = "$error_msg\n\nRequest Data:\n" . print_r($_REQUEST, true);
        @send_notification_email($admincontact, $subject, $body);
    }
    
    die($error_msg);
}

// For updates (when ID is provided), validate it
if (!empty($id)) {
    // Ensure ID is a valid integer to prevent SQL injection
    $id = (int)$id;
    if ($id <= 0) {
        die("ERROR: Invalid ID value: $id");
    }
}

// Prevent editing of completed work orders
if (!$is_insert && $table === 'work_orders') {
    $current_status_q = mysqli_query($connection, "SELECT wo_status FROM work_orders WHERE wo_id = $id LIMIT 1");
    if ($current_status_q && ($current_row = mysqli_fetch_assoc($current_status_q))) {
        $current_status = $current_row['wo_status'];
        if (in_array($current_status, ['Completed', 'Closed'])) {
            die("ERROR: Completed work orders cannot be edited. Work Order #$id is $current_status.");
        }
    }
}

// Check if this is an INSERT (new record, no ID) or UPDATE (existing record)
$is_insert = empty($id);

// Get column names using SHOW COLUMNS for mysqli
$fields_result = mysqli_query($connection, "SHOW COLUMNS FROM `$table`") or die('Could not get table columns: ' . mysqli_error($connection));
$columns_array = array();
while ($col = mysqli_fetch_assoc($fields_result)) {
    $columns_array[] = $col['Field'];
}

// number of columns
$columns = count($columns_array);

//if the value is set create a string of fields and values for the query SQL statement
// DEBUG: Log all form fields for work_orders
if ($table === 'work_orders') {
  error_log("[SAVE.PHP] Received REQUEST for work_orders: " . json_encode([
    'wo_id' => $_REQUEST['wo_id'] ?? 'NOT SET',
    'document' => $_REQUEST['document'] ?? 'NOT SET',
    'descriptive_text' => $_REQUEST['descriptive_text'] ?? 'NOT SET',
    'all_keys' => array_keys($_REQUEST)
  ]));
  @file_put_contents(__DIR__ . '/logs/save_detailed.log', 
    date('c') . " - REQUEST FIELDS: " . json_encode($_REQUEST) . "\n", FILE_APPEND);
    
  // CRITICAL: For new work orders, ensure wo_id is set
  // If wo_id is empty but we're doing an INSERT, generate one from next_wo
  if (empty($_REQUEST['wo_id']) && $is_insert) {
    error_log("[SAVE.PHP] wo_id is empty for new work_order INSERT, getting from next_wo");
    $nwo_q = mysqli_query($connection, "SELECT id FROM next_wo LIMIT 1");
    if ($nwo_q && $nwo_row = mysqli_fetch_assoc($nwo_q)) {
      $_REQUEST['wo_id'] = (int)$nwo_row['id'];
      error_log("[SAVE.PHP] Set wo_id to " . $_REQUEST['wo_id'] . " from next_wo");
      // Also update next_wo for next time
      $upd_nwo = "UPDATE next_wo SET id = id + 1";
      mysqli_query($connection, $upd_nwo);
      error_log("[SAVE.PHP] Incremented next_wo");
    }
  }
}

$field_names = "";
$values = "";
$field_arr = array();
$arry_str = "";

for ($i = 0; $i < $columns; $i++) //loop through all columns
{
  $fn = $columns_array[$i];

  // SPECIAL HANDLING: For work_orders, always include wo_id even if empty
  if ($table === 'work_orders' && $fn === 'wo_id') {
    if (!isset($_REQUEST['wo_id']) || $_REQUEST['wo_id'] === '') {
      // For new work orders, get wo_id from next_wo table
      $nwo_q = mysqli_query($connection, "SELECT id FROM next_wo LIMIT 1");
      if ($nwo_q && $nwo_row = mysqli_fetch_assoc($nwo_q)) {
        $_REQUEST['wo_id'] = (int)$nwo_row['id'];
        error_log("[SAVE.PHP] Generated wo_id: " . $_REQUEST['wo_id']);
        // Update next_wo for next time
        mysqli_query($connection, "UPDATE next_wo SET id = id + 1");
      }
    }
  }

  if(!empty($_REQUEST["$fn"]) || ($table === 'work_orders' && $fn === 'wo_id')) //if there is a variable name that matches a this column name...
    {
      // Skip 'unassigned' values for integer fields (mechanic_id, operator_id, etc.)
      if($_REQUEST["$fn"] === 'unassigned') {
        continue; // Skip this field, don't include it in the UPDATE/INSERT
      }

      if(!(stristr($fn,"date") === FALSE))
    {
      $_REQUEST["$fn"] = fix_date($_REQUEST["$fn"]);
    
    }
      $field_names .= $fn . ", "; //add this name to the list of field names
      ${$fn}="'" . mysqli_real_escape_string($connection, $_REQUEST["$fn"]) . "'";          //escape and quote the string
      $val = ${$fn};
      $values .= "$val, ";      //all the value of the form input to the list of values
      $arry_str = $fn . "=" . $val; 
      array_push($field_arr, $arry_str);
    }
}

// Handle NOT NULL fields that might not be submitted (add defaults)
$required_fields = array();
if ($table === 'trouble_calls') {
  $required_fields = array('description', 'parts', 'comments');
} else if ($table === 'work_orders') {
  $required_fields = array('description', 'descriptive_text');
}

// Validate that required fields are not empty
$missing_fields = array();
foreach ($required_fields as $req_field) {
  if (!in_array($req_field, $columns_array)) continue;
  
  if (empty($_REQUEST[$req_field])) {
    $missing_fields[] = $req_field;
  }
}

// If there are missing required fields, return an error
if (!empty($missing_fields)) {
  echo "<html><head><title>Error</title></head><body>";
  echo "<h2 style='color: red;'>ERROR: Missing Required Fields</h2>";
  echo "<p>The following required fields must be filled in:</p>";
  echo "<ul style='color: red; font-weight: bold;'>";
  foreach ($missing_fields as $field) {
    // Convert field names to readable format
    $readable_name = str_replace('_', ' ', ucfirst($field));
    echo "<li>$readable_name</li>";
  }
  echo "</ul>";
  echo "<p><a href='javascript:history.back()' style='color: blue;'>Click here to go back and fill in the missing fields</a></p>";
  echo "</body></html>";
  exit;
}

foreach ($required_fields as $req_field) {
  if (!in_array($req_field, $columns_array)) continue;
  
  // Check if this field was already added
  $field_exists = false;
  foreach ($field_arr as $fa) {
    if (strpos($fa, $req_field . '=') === 0) {
      $field_exists = true;
      break;
    }

    // Server-side request logging for work_order submissions
    if (isset($_REQUEST['document']) && $_REQUEST['document'] === 'work_order') {
      $safe_post = array();
      $safe_post['wo_id'] = isset($_REQUEST['wo_id']) ? (int)$_REQUEST['wo_id'] : 0;
      $safe_post['wo_status'] = isset($_REQUEST['wo_status']) ? substr($_REQUEST['wo_status'],0,64) : '';
      $safe_post['complete_date'] = isset($_REQUEST['complete_date']) ? substr($_REQUEST['complete_date'],0,32) : '';
      $safe_post['actor'] = isset($_SESSION['user']) ? $_SESSION['user'] : 'unknown';
      $logline = date('c') . " - SAVE.REQUEST - " . json_encode($safe_post) . "\n";
      @file_put_contents(__DIR__ . '/logs/save_requests.log', $logline, FILE_APPEND);
    }
  }
  
  if (!$field_exists && empty($_REQUEST[$req_field])) {
    // Field is required but wasn't submitted, add empty string
    $field_names .= $req_field . ", ";
    $$req_field = "''";
    $val = $$req_field;
    $values .= "$val, ";
    $arry_str = $req_field . "=" . $val;
    array_push($field_arr, $arry_str);
  }
}

//Strip the trailing comma and space
$field_names = substr($field_names, 0, -2);

$values = substr($values, 0, -2);

// Check if this is an INSERT (new record, no ID) or UPDATE (existing record)
$is_insert = empty($id);

if (!$is_insert) {
    // This is an UPDATE - check if the record exists
    $check_sql = "SELECT * from $table WHERE {$my}_id = $id";
    $sql_result = mysqli_query($connection, $check_sql) or die ("Could not execute query <BR> $check_sql");
    // If record doesn't exist, treat as INSERT
    if (mysqli_num_rows($sql_result) == 0) {
        $is_insert = true;
    }
}

if ($is_insert) {
    // INSERT new record
    $insert_sql = "INSERT INTO $table ($field_names) VALUES ($values)";
    
    // Log the INSERT for debugging
    if ($table === 'work_orders') {
        error_log("[SAVE.PHP] Attempting INSERT: " . $insert_sql);
        @file_put_contents(__DIR__ . '/logs/insert_attempts.log', date('c') . " - INSERT: $insert_sql\n", FILE_APPEND);
    }
    
    $sql_result = mysqli_query($connection, $insert_sql);
    if (!$sql_result) {
        // INSERT failed - log and report error
        $error_msg = "Could not insert data. SQL Error: " . mysqli_error($connection) . " <BR> SQL: " . htmlspecialchars($insert_sql);
        error_log("[SAVE.PHP] INSERT FAILED: " . $error_msg);
        @file_put_contents(__DIR__ . '/logs/insert_attempts.log', date('c') . " - FAILED: " . mysqli_error($connection) . " - SQL: $insert_sql\n", FILE_APPEND);
        die($error_msg);
    }

    // Audit: created new record (we'll use $saved_id after determining it)
    if ($table === 'work_orders') {
        error_log("[SAVE.PHP] INSERT SUCCESS for work_orders");
        
        $actor = mysqli_real_escape_string($connection, $_SESSION['user'] ?? ($_SESSION['username'] ?? 'unknown'));
        $details = mysqli_real_escape_string($connection, "created work_order");
        @mysqli_query($connection, "INSERT INTO audit_logs (actor, action, target_type, target_id, details) VALUES ('" . $actor . "', 'create', 'work_order', 0, '" . $details . "')");
    }

} else {

$existing_row = mysqli_fetch_assoc($sql_result);

$update_sql = "UPDATE $table SET " . implode(", ",$field_arr). " WHERE {$my}_id = $id";


$sql_result = mysqli_query($connection, $update_sql) or die ("Could not insert data <BR> $update_sql");

  // Audit: status change for work orders
  if ($table === 'work_orders' && isset($_REQUEST['wo_status'])) {
    $old = $existing_row['wo_status'] ?? '';
    $new = $_REQUEST['wo_status'];
    if ($old !== $new) {
      $actor = mysqli_real_escape_string($connection, $_SESSION['user'] ?? ($_SESSION['username'] ?? 'unknown'));
      $details = mysqli_real_escape_string($connection, "status_change from={$old} to={$new}");
      @mysqli_query($connection, "INSERT INTO audit_logs (actor, action, target_type, target_id, details) VALUES ('" . $actor . "', 'status_change', 'work_order', " . intval($id) . ", '" . $details . "')");
      
      // SLA Tracking: Set acknowledgment when status changes to Assigned or In Progress
      if (in_array($new, ['Assigned', 'In Progress'])) {
        set_acknowledged_timestamp($connection, $id);
      }
      
      // SLA Tracking: Set completion timestamp and calculate SLA when status changes to Completed
      if ($new === 'Completed') {
        set_completed_timestamp($connection, $id);
        
        // INTEGRATION: Auto-sync PM instance status when work order is completed
        // This prevents PM schedules from showing "Pending" when all their work is done
        include_once('pm_auto_sync_on_wo_complete.php');
        $sync_result = pm_auto_sync_on_wo_complete($connection, $id);
        if ($sync_result['success']) {
          error_log("[PM SYNC] Auto-synced " . $sync_result['synced_instances'] . " PM instance(s) for completed WO #$id");
        } else {
          error_log("[PM SYNC] Failed to sync PM instances for WO #$id: " . ($sync_result['error'] ?? 'Unknown error'));
        }
      }
    }
  }
}

// Determine saved id after insert/update
$inserted_id = mysqli_insert_id($connection);
if ($table === 'work_orders') {
    $saved_id = $_REQUEST['wo_id'];
} else {
    $saved_id = ($inserted_id && $inserted_id > 0) ? $inserted_id : $id;
}

// Update the audit log entry for create() if it was inserted with target_id 0 above
if ($table === 'work_orders') {
  // If there is an audit entry with target_id=0 and action=create by this actor recently, update it to the real id
  $actor_check = mysqli_real_escape_string($connection, $_SESSION['user'] ?? ($_SESSION['username'] ?? 'unknown'));
  @mysqli_query($connection, "UPDATE audit_logs SET target_id = " . intval($saved_id) . " WHERE action='create' AND target_type='work_order' AND target_id=0 AND actor='" . $actor_check . "'");
}

// If we just saved a work order and its status is Completed, update any related PM instances
if ($table === 'work_orders' && $saved_id) {
  // LOG: Entry point for completion workflow
  $log_entry = date('c') . " - === SAVE.PHP COMPLETION CHECK ===\n";
  $log_entry .= "  WO#$saved_id, table=$table, saved_id=$saved_id\n";
  @file_put_contents(__DIR__ . '/logs/completion_workflow.log', $log_entry, FILE_APPEND);
  
  // reload work order to get latest fields
  $woq = mysqli_query($connection, "SELECT wo_status, complete_date FROM work_orders WHERE wo_id = " . (int)$saved_id);
  if ($woq && ($worow = mysqli_fetch_assoc($woq))) {
    // LOG: Status from database
    @file_put_contents(__DIR__ . '/logs/completion_workflow.log', date('c') . " - DB Query Result: wo_status='{$worow['wo_status']}', complete_date='{$worow['complete_date']}'\n", FILE_APPEND);
    
    if (isset($worow['wo_status']) && in_array($worow['wo_status'], ['Completed', 'Closed'])) {
      // LOG: Completion triggered
      @file_put_contents(__DIR__ . '/logs/completion_workflow.log', date('c') . " - ✅ STATUS IS COMPLETED - TRIGGERING EMAIL WORKFLOW\n", FILE_APPEND);
      $completed_date = !empty($worow['complete_date']) ? $worow['complete_date'] : date('Y-m-d');
      $upd = "UPDATE pm_instances SET status='Completed', completed_date='" . mysqli_real_escape_string($connection, $completed_date) . "' WHERE wo_id = " . (int)$saved_id;
      mysqli_query($connection, $upd);
      // Also update professional PM table if present (pm_schedule_log)
      $chk_pm_log = mysqli_query($connection, "SHOW TABLES LIKE 'pm_schedule_log'");
      if ($chk_pm_log && mysqli_num_rows($chk_pm_log) > 0) {
        $upd_log = "UPDATE pm_schedule_log SET status='Completed', completed_date='" . mysqli_real_escape_string($connection, $completed_date) . "' WHERE wo_id = " . (int)$saved_id;
        mysqli_query($connection, $upd_log);
        @file_put_contents(__DIR__ . '/logs/completion_workflow.log', date('c') . " - pm_schedule_log updated for WO#{$saved_id}\n", FILE_APPEND);
      }
        // Resolve any active escalations/warnings related to this work order (only update columns that exist)
        $actor = mysqli_real_escape_string($connection, $_SESSION['user'] ?? ($_SESSION['username'] ?? 'system'));
        $esc_cols_q = mysqli_query($connection, "SHOW COLUMNS FROM work_order_escalations");
        $esc_cols = array();
        while ($c = mysqli_fetch_assoc($esc_cols_q)) { $esc_cols[] = $c['Field']; }
        $esc_sets = array();
        $esc_sets[] = "status='resolved'";
        if (in_array('resolved_at', $esc_cols)) { $esc_sets[] = 'resolved_at=NOW()'; }
        if (in_array('resolved_by', $esc_cols)) { $esc_sets[] = "resolved_by='" . $actor . "'"; }
        @mysqli_query($connection, "UPDATE work_order_escalations SET " . implode(',', $esc_sets) . " WHERE wo_id = " . (int)$saved_id . " AND status!='resolved'");
        // Clear escalation flag on work order
        @mysqli_query($connection, "UPDATE work_orders SET escalated=0 WHERE wo_id = " . (int)$saved_id);
        // Insert an audit log entry for completion
        $details = mysqli_real_escape_string($connection, "completed work_order");
        @mysqli_query($connection, "INSERT INTO audit_logs (actor, action, target_type, target_id, details, created_at) VALUES ('" . $actor . "', 'complete', 'work_order', " . intval($saved_id) . ", '" . $details . "', NOW())");
        // Send notification emails: admin and assigned mechanic (if email present)
        // Attempt to generate a PDF version of the work order to attach
        $pdf_data = null;
        $pdf_name = 'work_order_' . intval($saved_id) . '.pdf';

        // Prefer server-side ezPDF generation (produces a real PDF binary)
        $ezpath = __DIR__ . '/libraries/ezpdf/class.ezpdf.php';
        if (file_exists($ezpath)) {
          // suppress warnings/deprecations from the library during include/usage
          $prev_display = ini_get('display_errors');
          $prev_err = error_reporting();
          @ini_set('display_errors', '0');
          error_reporting($prev_err & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED);
          include_once $ezpath;
          // fetch the full work order record for printing
          $pq = mysqli_query($connection, "SELECT wo.*, m.lname, m.fname, p.description AS priority FROM work_orders AS wo LEFT JOIN mechanics AS m ON wo.mechanic_id = m.id LEFT JOIN priority AS p ON wo.priority = p.priority WHERE wo.wo_id = " . (int)$saved_id . " LIMIT 1");
          if ($pq && ($pwo = mysqli_fetch_object($pq))) {
            try {
              $pdf = new Cezpdf('LETTER');
              $pdf->selectFont(__DIR__ . '/libraries/ezpdf/fonts/Helvetica.afm');
              $pdf->ezSetMargins(38,38,38,38);
              $pdf->ezText('WO# ' . $pwo->wo_id, 20 ,array('justification'=>'right'));
              $pdf->ezText('Work Order', 32 ,array('justification'=>'center','leading'=> 50));
              $pdf->rectangle(38,580,536,80);
              $pdf->addText(48,640, 12, 'Priority: <c:uline>' . ($pwo->priority ?? '') . '</c:uline>');
              $pdf->addText(225,640, 12, 'Status: <c:uline>' . ($pwo->wo_status ?? '') . '</c:uline>');
              $pdf->addText(355,640, 12, 'External Reference #: <c:uline>' . ($pwo->ref_no ?? '') . '</c:uline>');
              $pdf->addText(48,615, 12, 'Requested By: <c:uline>' . ($pwo->requestor ?? '') . '</c:uline>');
              $pdf->addText(306,615, 12, 'Approved By: <c:uline>' . ($pwo->approval ?? '') . '</c:uline>');
              $pdf->addText(48,590, 12, 'Date Requested: <c:uline>' . ($pwo->submit_date ?? '') . '</c:uline>');
              $pdf->addText(306,590, 12, 'Date Needed: <c:uline>' . ($pwo->needed_date ?? '') . '</c:uline>');

              $pdf->rectangle(38,100,248,460);
              $pdf->line(38,532,286,532);
              $pdf->addText(110, 540, 18, '<b>Job Details</b>');
              $pdf->ezSetY(532);
              $pdf->ezText('<b>Equipment:</b> ' . ($pwo->equipment ?? ''), 12,array('leading'=>16, 'justification'=>'left','aleft'=>40,'aright'=>276));
              $pdf->ezSetY(500);
              $pdf->ezText('<b>Coordinating Instructions:</b>', 12,array('leading'=>20, 'justification'=>'left','aleft'=>40,'aright'=>276));
              $pdf->ezText($pwo->coordinating_instructions ?? '' ,12,array('justification'=>'full','aleft'=>48,'aright'=>276));
              $pdf->ezSetY(350);
              $pdf->ezText('<b>Work Description:</b>', 12,array('leading'=>20, 'justification'=>'left','aleft'=>40,'aright'=>276));
              $pdf->ezText($pwo->description ?? '' ,12,array('justification'=>'full','aleft'=>48,'aright'=>276));

              $pdf->rectangle(326,100,248,460);
              $pdf->line(326,532,574,532);
              $pdf->addText(400, 540, 18, '<b>Completion</b>');
              $pdf->ezSetY(532);
              $pdf->ezText('<b>Craftsman:</b> ' . trim(($pwo->lname ?? '') . ' ' . ($pwo->fname ?? '')), 12,array('leading'=>24, 'justification'=>'left','aleft'=>328,'aright'=>564));
              $pdf->ezText('<b>Hours:</b> ' . ($pwo->hours ?? ''), 12,array('leading'=>24, 'justification'=>'left','aleft'=>328,'aright'=>564));
              $pdf->ezText('<b>Complete Date:</b> ' . ($pwo->complete_date ?? ''), 12,array('leading'=>24, 'justification'=>'left','aleft'=>328,'aright'=>564));
              $pdf->ezText('<b>Action Taken:</b>', 12,array('leading'=>24, 'justification'=>'left','aleft'=>328,'aright'=>564));
              $pdf->ezText($pwo->action ?? '' , 12,array('justification'=>'left','aleft'=>336,'aright'=>556));
              $pdf->line(326,130,574,130);
              $pdf->ezSetY(135);
              $pdf->ezText('<b>Inspected By:</b> ' . ($pwo->inspected_by ?? ''), 12,array('leading'=>24, 'justification'=>'left','aleft'=>328,'aright'=>564));
              $pdf->addText(38,80,12, '<b>Notes:</b>');

              // capture the PDF binary into a string
              $pdf_data = $pdf->ezOutput();
            } catch (\Throwable $e) {
              $pdf_data = null;
            }
          // restore error reporting after using ezPDF
          @ini_set('display_errors', $prev_display);
          error_reporting($prev_err);
          }
        } else {
          // fallback: try to fetch printable page (may be HTML) over HTTP
          $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '127.0.0.1:3000';
          $print_url = (strpos($host, 'http') === 0 ? $host : 'http://' . $host) . dirname($_SERVER['REQUEST_URI']) . '/print_wo.php?wo_id=' . intval($saved_id);
          $ctx = stream_context_create(['http' => ['timeout' => 10]]);
          $maybe_pdf = @file_get_contents($print_url, false, $ctx);
          if ($maybe_pdf !== false && strlen($maybe_pdf) > 100) {
            $pdf_data = $maybe_pdf;
          }
        }

        // If ezPDF didn't produce a PDF, try fetching the printable PDF endpoint (print.php) over HTTP first, then fall back to print_wo.php
        if (empty($pdf_data)) {
          $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '127.0.0.1:3000';
          $base = (strpos($host, 'http') === 0 ? $host : 'http://' . $host) . dirname($_SERVER['REQUEST_URI']);
          $ctx = stream_context_create(['http' => ['timeout' => 10]]);
          // Prefer print.php which streams a PDF via ezPDF
          $try_print = $base . '/print.php?wo_id=' . intval($saved_id);
          $maybe_pdf = @file_get_contents($try_print, false, $ctx);
          if ($maybe_pdf !== false && strlen($maybe_pdf) > 100 && substr($maybe_pdf, 0, 4) === '%PDF') {
            $pdf_data = $maybe_pdf;
          } else {
            // fallback to HTML printable page (may not be PDF)
            $try_html = $base . '/print_wo.php?wo_id=' . intval($saved_id);
            $maybe_html = @file_get_contents($try_html, false, $ctx);
            if ($maybe_html !== false && strlen($maybe_html) > 100) {
              $pdf_data = $maybe_html;
            }
          }
          }

        // Gather work order details
        $wo_row_q = mysqli_query($connection, "SELECT wo_id, descriptive_text, requestor, mechanic_id, complete_date FROM work_orders WHERE wo_id = " . (int)$saved_id . " LIMIT 1");
        if ($wo_row_q && ($wo_row = mysqli_fetch_assoc($wo_row_q))) {
          $subject = "Work Order " . intval($saved_id) . " Completed";
          $body = "Work Order " . intval($saved_id) . " has been marked Completed.\n\n";
          $body .= "Description: " . ($wo_row['descriptive_text'] ?? '') . "\n";
          $body .= "Completed By: " . $actor . "\n";
          $body .= "Complete Date: " . ($wo_row['complete_date'] ?? date('Y-m-d')) . "\n\n";
          $body .= "View: " . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . dirname($_SERVER['REQUEST_URI']) . "/work_order.php?wo_id=" . intval($saved_id) . "\n";

          // Ensure we have a real PDF to attach; if not, build a minimal PDF and save it
          if (empty($pdf_data) || substr($pdf_data, 0, 4) !== '%PDF') {
            $title = 'Work Order ' . intval($saved_id);
            $lines = array();
            $lines[] = 'WO#: ' . intval($saved_id);
            $lines[] = 'Description: ' . ($wo_row['descriptive_text'] ?? '');
            $lines[] = 'Requestor: ' . ($wo_row['requestor'] ?? '');
            $lines[] = 'Completed By: ' . $actor;
            $lines[] = 'Complete Date: ' . ($wo_row['complete_date'] ?? date('Y-m-d'));
            $pdf_data = build_simple_pdf($title, $lines);
          }
          if (!empty($pdf_data)) {
            $attach_dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'attachments';
            if (!is_dir($attach_dir)) { @mkdir($attach_dir, 0755, true); }
            @file_put_contents($attach_dir . DIRECTORY_SEPARATOR . $pdf_name, $pdf_data);
          }
          // From header
          $from = isset($SMTP_FROM_EMAIL) ? $SMTP_FROM_EMAIL : 'no-reply@example.com';
          $headers = 'From: ' . $from . "\r\n" . 'X-Mailer: PHP/' . phpversion();

          // Send to admin (use PHPMailer SMTP when available) with PDF attachment when available
          if (!empty($admincontact)) {
            @file_put_contents(__DIR__ . '/logs/completion_workflow.log', date('c') . " - WO#$saved_id SENDING EMAIL to admin: $admincontact\n", FILE_APPEND);
            send_notification_email($admincontact, $subject, $body, $from, null, $pdf_data, $pdf_name);
            @file_put_contents(__DIR__ . '/logs/completion_workflow.log', date('c') . " - WO#$saved_id ADMIN EMAIL SENT\n", FILE_APPEND);
          } else {
            @file_put_contents(__DIR__ . '/logs/completion_workflow.log', date('c') . " - WO#$saved_id WARNING: admincontact is empty\n", FILE_APPEND);
          }

          // If a mechanic is assigned and mechanics table has an email, notify them
          if (!empty($wo_row['mechanic_id'])) {
            $mech_id = (int)$wo_row['mechanic_id'];
            $meq = mysqli_query($connection, "SELECT email, fname, lname FROM mechanics WHERE id = " . $mech_id . " LIMIT 1");
            if ($meq && ($mrow = mysqli_fetch_assoc($meq))) {
              $mech_email = $mrow['email'] ?? '';
              if (!empty($mech_email)) {
                @file_put_contents(__DIR__ . '/logs/completion_workflow.log', date('c') . " - WO#$saved_id SENDING EMAIL to mechanic: $mech_email\n", FILE_APPEND);
                $body_mech = "Hello " . trim(($mrow['fname'] ?? '') . ' ' . ($mrow['lname'] ?? '')) . ",\n\n" . $body;
                send_notification_email($mech_email, $subject, $body_mech, $from, null, $pdf_data, $pdf_name);
                @file_put_contents(__DIR__ . '/logs/completion_workflow.log', date('c') . " - WO#$saved_id MECHANIC EMAIL SENT\n", FILE_APPEND);
              }
            }
          }
        }
    }
  }
}

// Handle spare parts for work orders
if ($table === 'work_orders' && $saved_id && isset($_REQUEST['spare_parts'])) {
    require_once("./SparePartsIntegrationManager.php");
    $spareManager = new SparePartsIntegrationManager($connection);

    // Get equipment_id from the work order
    $equip_query = mysqli_query($connection, "SELECT equipment FROM work_orders WHERE wo_id = " . (int)$saved_id);
    $equipment_id = 0;
    if ($equip_query && ($equip_row = mysqli_fetch_assoc($equip_query))) {
        $equipment_id = (int)$equip_row['equipment'];
    }

    $used_by = isset($_SESSION['user']) ? $_SESSION['user'] : 'system';

    foreach ($_REQUEST['spare_parts'] as $spare_index => $spare_data) {
        if (empty($spare_data['spare']) || empty($spare_data['quantity'])) {
            continue; // Skip empty entries
        }

        // Parse the spare selection
        $spare_parts = explode('_', $spare_data['spare'], 2);
        if (count($spare_parts) !== 2) continue;

        $spare_type = $spare_parts[0]; // 'spare' or 'part'
        $spare_id = (int)$spare_parts[1];

        $spare_info = [
            'spare_id' => ($spare_type === 'spare') ? $spare_id : null,
            'part_id' => ($spare_type === 'part') ? $spare_id : null,
            'part_number' => $spare_data['part_number'] ?? '',
            'description' => $spare_data['description'] ?? '',
            'quantity' => (float)$spare_data['quantity'],
            'unit_cost' => (float)$spare_data['unit_cost'],
            'used_by' => $used_by,
            'notes' => $spare_data['notes'] ?? '',

            // Lifecycle tracking fields
            'action_type' => $spare_data['action_type'] ?? 'consumption',
            'serial_number' => $spare_data['serial_number'] ?? null,
            'batch_lot_number' => $spare_data['batch_lot_number'] ?? null,
            'location_on_equipment' => $spare_data['location_on_equipment'] ?? null,
            'expected_lifespan_days' => $spare_data['expected_lifespan_days'] ?? null,
            'expected_lifespan_hours' => $spare_data['expected_lifespan_hours'] ?? null,
            'warranty_expiry' => $spare_data['warranty_expiry'] ?? null,
            'installation_notes' => $spare_data['lifecycle_notes'] ?? null,
            'is_installation' => ($spare_data['action_type'] ?? 'consumption') === 'installation'
        ];

        try {
            $wop_id = $spareManager->addSpareToWorkOrder($saved_id, $equipment_id, $spare_info);

            // Handle replacements
            if (($spare_data['action_type'] ?? 'consumption') === 'replacement' && !empty($spare_data['installation_id'])) {
                $replacement_data = [
                    'installation_id' => (int)$spare_data['installation_id'],
                    'equipment_id' => $equipment_id,
                    'replaced_by' => $used_by,
                    'replaced_date' => date('Y-m-d H:i:s'),
                    'wo_id' => $saved_id,
                    'wop_id' => $wop_id,
                    'replacement_reason' => $spare_data['replacement_reason'] ?? 'failure',
                    'failure_mode' => $spare_data['failure_mode'] ?? null,
                    'failure_analysis' => $spare_data['lifecycle_notes'] ?? null,
                    'actual_lifespan_hours' => $spare_data['operating_hours_at_replacement'] ?? null,
                    'operating_hours_at_replacement' => $spare_data['operating_hours_at_replacement'] ?? null,
                    'condition_when_removed' => $spare_data['condition_when_removed'] ?? 'failed',
                    'reuse_potential' => 0, // Default to no reuse
                    'scrap_value' => 0, // Default to no scrap value
                    'replacement_notes' => $spare_data['lifecycle_notes'] ?? null
                ];

                $spareManager->recordReplacement($saved_id, $equipment_id, $replacement_data);
            }
        } catch (Exception $e) {
            error_log("[SAVE.PHP] Error processing spare part: " . $e->getMessage());
            // Continue processing other spares even if one fails
        }
    }
}

// Handle file attachments for work orders
if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
    $upload_dir = get_tenant_upload_path('attachments');
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }

    $files = $_FILES['attachments'];
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $original = basename($files['name'][$i]);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $original);
          $target_name = 'wo_' . (int)$saved_id . '_' . time() . '_' . $safe;
        $target_path = $upload_dir . DIRECTORY_SEPARATOR . $target_name;
        if (move_uploaded_file($files['tmp_name'][$i], $target_path)) {
          if (function_exists('mime_content_type')) {
            $detected_mime = mime_content_type($target_path);
          } elseif (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = finfo_file($finfo, $target_path);
            finfo_close($finfo);
          } else {
            $detected_mime = 'application/octet-stream';
          }
          $mime = mysqli_real_escape_string($connection, $detected_mime);
          $fn = mysqli_real_escape_string($connection, $original);
          // store relative path to tenant-specific attachments directory
          $fp = mysqli_real_escape_string($connection, 'storage/uploads/tenant_' . tenant_id() . '/attachments/' . $target_name);
          $u = isset($_SESSION['user']) ? mysqli_real_escape_string($connection, $_SESSION['user']) : '';
          $ins = "INSERT INTO work_order_attachments (wo_id, filename, filepath, mime, uploaded_by) VALUES (" . (int)$saved_id . ", '$fn', '$fp', '$mime', '$u')";
          mysqli_query($connection, $ins);
          $attach_id = mysqli_insert_id($connection);
          $actor = mysqli_real_escape_string($connection, $_SESSION['user'] ?? ($_SESSION['username'] ?? 'unknown'));
          $details = mysqli_real_escape_string($connection, "filename={$fn};wo_id={$saved_id}");
          @mysqli_query($connection, "INSERT INTO audit_logs (actor, action, target_type, target_id, details) VALUES ('" . $actor . "', 'upload_attachment', 'attachment', " . intval($attach_id) . ", '" . $details . "')");
        }
    }
}

// Sanitize wo_status to allowed list
if ($table === 'work_orders') {
  $allowed = ['Requested','Pending Approval','Approved','Assigned','In Progress','QA','Suspended','Completed','Closed','Rejected','Hot Job'];
  if (isset($_REQUEST['wo_status']) && !in_array($_REQUEST['wo_status'], $allowed)) {
    // Force to Pending Approval if invalid
    mysqli_query($connection, "UPDATE work_orders SET wo_status='Pending Approval' WHERE wo_id=" . (int)$id);
  }
  // Save QA fields if provided; treat empty values as NULL to avoid invalid DATE inserts
  if (isset($_REQUEST['qa_by']) || isset($_REQUEST['qa_date'])) {
    $qa_by = (isset($_REQUEST['qa_by']) && $_REQUEST['qa_by'] !== '') ? "'" . mysqli_real_escape_string($connection, $_REQUEST['qa_by']) . "'" : "NULL";
    $qa_date = (isset($_REQUEST['qa_date']) && $_REQUEST['qa_date'] !== '') ? "'" . mysqli_real_escape_string($connection, $_REQUEST['qa_date']) . "'" : "NULL";
    mysqli_query($connection, "UPDATE work_orders SET qa_by=$qa_by, qa_date=$qa_date WHERE wo_id = " . (int)$saved_id);
  }
}

mysqli_close($connection);

/*Return to the last list that the user was viewing or a default list*/
$previous = $_SESSION["last_query"] ?? '';
if(empty($previous))
{
  $previous = "list.php";
}

// CRITICAL: Verify that work order was actually created before redirecting
if ($table === 'work_orders' && $saved_id) {
    // Reconnect to verify
    $verify_connection = mysqli_connect($hostName, $userName, $password, $databaseName);
    if ($verify_connection) {
        $verify_q = "SELECT wo_id FROM work_orders WHERE wo_id = " . intval($saved_id) . " LIMIT 1";
        $verify_result = mysqli_query($verify_connection, $verify_q);
        if (!$verify_result || mysqli_num_rows($verify_result) == 0) {
            // CRITICAL FAILURE: Work order was not actually created!
            error_log("[SAVE.PHP] CRITICAL: Work order #$saved_id was not found in database after INSERT");
            @file_put_contents(__DIR__ . '/logs/insert_attempts.log', date('c') . " - VERIFICATION FAILED: WO#$saved_id does not exist\n", FILE_APPEND);
            die("<h2 style='color:red;'>CRITICAL ERROR</h2><p>Work Order #$saved_id could not be created in the database. <br>This indicates a database configuration or permission issue. <br>Please contact your administrator and provide them with the error logs.<br><br><a href='work_order.php?wo_id=new'>Create Another Work Order</a></p>");
        } else {
            error_log("[SAVE.PHP] VERIFIED: Work order #$saved_id exists in database");
        }
        mysqli_close($verify_connection);
    }
}

// Use saved id for user feedback and redirect
echo "<script type=text/javascript>\n\n<!--\n";
echo "alert(\"Work Order $saved_id Saved\");";

/* if we got to a work order form by typing in a search string that returned one
 * result, we don't want to refresh the main screen. doing so would reopen the
 * the work order that we just closed
 */
if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'search.php') !== false) {
  echo 'window.opener.location.reload();';
}


if(isset($_POST['done']))
{
  // If this form was opened as a popup, close it and refresh opener.
  echo "if (window.opener && !window.opener.closed) { window.opener.location.reload(); window.close(); } else { ";
  $redirect = $next_link;
  if ($table === 'work_orders') {
      $redirect = $next_link . '?wo_id=' . intval($saved_id);
  }
  echo "window.location.href = '$redirect'; }";
}
else
{
    $redirect = $next_link;
    if ($table === 'work_orders') {
        $redirect = $next_link . '?wo_id=' . intval($saved_id);
    }
    echo "window.location.href = '$redirect'";
}

echo "
-->
</script>

Close this window (or enable javascript and it will be done automagically)";

?>


