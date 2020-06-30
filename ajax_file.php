<?php

$invoiceId = $_POST['refrence'],FILTER_SANITIZE_STRING);		// transactionID
$_SESSION['invoiceId'] = $invoiceId;


$gatewayParams['terminal_id'] = 11111;		// Replace with terminalld
$gatewayParams['merchant_id'] = 11111;		// Replace with merchantld
$gatewayParams['secret_key'] = 'xxxxxxxxxxxxxxxxxx';	// Replace with secret_key
$gatewayParams['testMode'] = 'on';

function getTimeNow()
{
    $now = new DateTime();
    $time = $now->format('Y-m-d H:i:s');
    $date = strtotime($time);
    $day = date('d', $date);
    $month = date('m', $date);
    $year = date('Y', $date);
    return $year . $month . $day . '';
}

function getTime()
{
    $now = new DateTime();
    $time = $now->format('Y-m-d H:i:s');
    $date = strtotime($time);
    $day = date('d', $date);
    $month = date('m', $date);
    $year = date('y', $date);
    $hour = date('H', $date);
    $minutes = date('i', $date);
    $seconds = date('s', $date);
    return $year . $month . $day . $hour . $minutes . $seconds . '';

}


function hexToStr($hex)
{
    $string = '';
    for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
        $string .= chr(hexdec($hex[$i] . $hex[$i + 1]));
    }
    return $string;
}


function sendRequest($gateway_url, $request_string)
{
    // Create a new cURL resource
    $ch = curl_init($gateway_url);
    $payload = json_encode($request_string);
    // Attach encoded JSON string to the POST fields
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    // Set the content type to application/json
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    // Return response instead of outputting
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Execute the POST request
    $result = curl_exec($ch);
    // Close cURL resource
    curl_close($ch);
    return $result;
}


function generateSecureHash($time, $gatewayParams)
{
    $merchantId = $gatewayParams['merchant_id'];
    $terminalId = $gatewayParams['terminal_id'];
    $secretKey = $gatewayParams['secret_key'];
    $hashing = "DateTimeLocalTrxn=$time&MerchantId=$merchantId&TerminalId=$terminalId";
    return strtoupper(hash_hmac('sha256', $hashing, hexToStr($secretKey)));
}


function complete_transaction($gatewayParams)
{
    $data['success'] = false;
    $time = getTime();
    $secureHash = generateSecureHash($time, $gatewayParams);
    $request_string = array(
        'SecureHash' => $secureHash,
        'DateTimeLocalTrxn' => $time,
        'TerminalId' => $gatewayParams['terminal_id'],
        'MerchantId' => $gatewayParams['merchant_id'],
        'DateFrom' => getTimeNow(),
        'DateTo' => getTimeNow(),
        'TransactionId' => $_SESSION['invoiceId'],
        'FetchType' => "0",
        'DisplayStart' => 0,
        'DisplayLength' => 5
    );
    if ($gatewayParams['testMode'] == 'off') {
        $url = "https://cube.paysky.io/Cube/PayLink.svc/api/FilterTransactions";
    } else {
        $url = "https://grey.paysky.io/Cube/PayLink.svc/api/FilterTransactions";
    }

    $getdataresponse = sendRequest($url, $request_string);
    $object = json_decode($getdataresponse);

    if ($object != null) {
        if ($object->TotalCountAllTransaction > 0) {
            $isPaymentApproved = false;
            $transactions = $object->Transactions;
            foreach ($transactions as $transaction) {
                $dateTransactions = $transaction->DateTransactions;
                foreach ($dateTransactions as $dateTransaction) {
                    if ($dateTransaction->TransactionId == $_SESSION['invoiceId']) {		// get the transaction by TransactionId
                        if ($dateTransaction->Status == 'Approved') {
                            $isPaymentApproved = true;
                            break;
                        } else {
                            $isPaymentApproved = false;
                            break;
                        }
                    }
                }

            }
            if ($isPaymentApproved) {
                $data['success'] = true;
                $data['memberid'] = $object->Transactions[0]->DateTransactions[0]->MerchantReference;		// Merchant Reference Used as userid ( change it to any useful data u need )
                $data['gatewayName'] = 'paysky';
                $data['gatewayTransactionId'] = $object->Transactions[0]->DateTransactions[0]->TransactionId;
                $paymentAmount = (int)$object->Transactions[0]->DateTransactions[0]->AmountTrxn;
                $data['amount'] = $paymentAmount / 100;
                // do something with this data
            } else {
                $data['error'] = 'Hash Verification Failure';
                $data['success'] = false;
            }


        } else {
            $data['error'] = 'Hash Verification Failure';
            $data['success'] = false;
        }

    }
    return array_filter($data);
}

header('Content-Type: application/json');
echo json_encode(complete_transaction($gatewayParams));			//retrun json data

?>