<?php
header("Content-Type: application/json");
include 'db.php';
include 'insert-records.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo, $input);
        break;
    case 'PUT':
        handlePut($pdo, $input);
        break;
    case 'DELETE':
        handleDelete($pdo, $input);
        break;
    default:
        echo json_encode(['message' => 'Invalid request method']);
        break;
}

function handleGet($pdo) {
    $sql = "SELECT * FROM quote";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result);
}

function handlePost($pdo, $input) {

    $fullname=$input['Full_Name'];
    $companyname=$input['Company_Name'];
    $email=$input['Email'];
    $contactnumber=$input['Contact_Number'];
    $coverRequired=$input['Cover_Required'];
    $sctc=$input['Scaffolding_Type_Conducted'];
    $heightcarrriesd=$input['Is_Work_Carried_Out_at_Heights_Exceeding_10_Meters'];
    $haveinsurance=$input['Do_you_currently_have_insurance'];
    


    $sql = "INSERT INTO quote (Full_Name, Company_Name,Email,Contact_Number,Cover_Required,Scaffolding_Type_Conducted,Is_Work_Carried_Out_at_Heights_Exceeding_10_Meters,Do_you_currently_have_insurance) VALUES 
    (:Full_Name, :Company_Name,:Email,:Contact_Number,:Cover_Required,:Scaffolding_Type_Conducted,:Is_Work_Carried_Out_at_Heights_Exceeding_10_Meters,:Do_you_currently_have_insurance)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'Full_Name' => $fullname,
         'Company_Name' =>$companyname,
         'Email' =>$email,
         'Contact_Number' =>$contactnumber,
         'Cover_Required' =>$coverRequired,
         'Scaffolding_Type_Conducted' =>$sctc,
         'Is_Work_Carried_Out_at_Heights_Exceeding_10_Meters' =>$heightcarrriesd,
         'Do_you_currently_have_insurance' => $haveinsurance
        ]);
        if($stmt) {
            //After DB Insert Sending the Information to Zoho
            addRecordToZoho($fullname,$companyname,$contactnumber,$email);
            // sendEmail($fullname,$companyname,$email,$contactnumber,
            // $coverRequired,$sctc,$heightcarrriesd,$haveinsurance);
    
            echo json_encode(['message' => 'Quote created successfully','code'=>'200']);
        };
      
}

function sendEmail($fullname,$cname,$cemail,$contact,$cover,$scalfoltype,$hieght,$haveinsurance){
    
// Multiple recipients separated by comma

$to = 'lahari@smartsolutionsdigi.com,narasimha@smartsolutionsdigi.com';

// Subject

$subject = 'ScalfoldingInsurance Quote';
// Message

$message = "

<html>

<head>

<title>Insurance Quote</title>
<style>
table {
  font-family: arial, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

td, th {
  border: 1px solid #dddddd;
  text-align: left;
  padding: 8px;
}

tr:nth-child(even) {
  background-color: #dddddd;
}
</style>
</head>

<body>


<table>
  <tr>
    <th>Full Name</th>
    <th>Company Name</th>
    <th>Email</th>
    <th>Contact Number</th>
    <th>How Much Cover Required</th>
    <th>Type of Scaffolding Works Conductedd</th>
       <th>Is Work Carried Out at Heights Exceeding 10 Meters</th>
        <th>Do you currently have insurance in places</th>
  </tr>
  <tr>

    <td>".$fullname."</td>
     <td>".$cname."</td>
 <td>".$cemail."</td>
 <td>".$contact."</td>
 <td>".$cover."</td>
 <td>".$scalfoltype."</td>
 <td>".$hieght."</td>
 <td>".$haveinsurance."</td>

    
  </tr>
</table>

</body>

</html>

";

// To send HTML emails, remember to set the Content-type header

// $headers = "MIME-Version: 1.0" . "\r\n";

// $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

$headers =  'MIME-Version: 1.0' . "\r\n"; 
$headers .= 'From: scaffolding insurance Website <info@scaffoldinginsurance.com>' . "\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n"; 

// Other additional headers

// $headers[] = 'To: John <john@example.com>, Mary <mary@example.com>';

// $headers[] = 'From: Supply Reminders <reminders@example.com>';

// $headers[] = 'Cc: name@example.com';

// $headers[] = 'Bcc: name@example.com';

// Mail it

$mailsent=mail($to, $subject, $message, $headers);

}
function handlePut($pdo, $input) {
    $sql = "UPDATE quote SET name = :name, email = :email WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['name' => $input['name'], 'email' => $input['email'], 'id' => $input['id']]);
    echo json_encode(['message' => 'User updated successfully']);
}

function handleDelete($pdo, $input) {
    $sql = "DELETE FROM quote WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $input['id']]);
    echo json_encode(['message' => 'User deleted successfully']);
}
?>