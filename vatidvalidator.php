<?php

// VAT ID Validator with psql DB access
// 2016-08-19-2150 nils.clausen@c-bi.de
// (c) Clausen Business Intelligence GmbH

$conn = pg_pconnect("host=financedb.c-bi.de port=5432 dbname=wmdb user=postgres password=****");
if (!$conn)
{
  echo "Error. Cannot connect to database wmdb.\n";
  exit;
}

// Initialise check table
$result = pg_query($conn, "delete from dm.z_rv_vat_checking where checked_at = now()::date");

// Read table data as input
$result = pg_query($conn, "SELECT user_id,source_table,UPPER(source_vat_id) as source_vat_id FROM dm.z_vw_rv_vat_checkin
g_selection");

while($row = pg_fetch_row($result))
{
  // echo "vatin: " . $row["vatin"] . "<br>";
  $source_vat_id = $row[2]; // formerly vatin
  $vat_id = $source_vat_id;
  $vat_id = str_replace(array(' ', '.', '-', ',', ', '), '', trim($vat_id));
  $cc = substr($vat_id, 0, 2);
  $vn = substr($vat_id, 2);
  $client = new SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl");

  if($client)
  {
    $params = array('countryCode' => $cc, 'vatNumber' => $vn);
    try 
    {
      $r = $client->checkVat($params);
      if($r->valid == true)
      {
        // VAT-ID is valid
        // echo 'VAT ID ' . $vat_id . '--> OK<br>';
        $query = pg_query($conn, "INSERT INTO dm.z_rv_vat_checking (user_id,source_table,source_vat_id,check_vat_id,check_result,checked_at) VALUES ($row[0],'$row[1]','$source_vat_id','$vat_id',1,now()::date)");
      } 
      else 
      {
        // VAT-ID is NOT valid
        // echo 'VAT ID ' . $vat_id . '--> <b>NOT OK!</b></br>';
	$query = pg_query($conn, "INSERT INTO dm.z_rv_vat_checking (user_id,source_table,source_vat_id,check_vat_id,check_result,checked_at) VALUES ($row[0],'$row[1]','$source_vat_id','$vat_id',0,now()::date)");
      }
    }
    catch(SoapFault $e) 
    {
      // SOAP Server not reachable or other error
        // echo 'Error, see message: '.$e->faultstring;
        $query = pg_query($conn, "INSERT INTO dm.z_rv_vat_checking (user_id,source_table,source_vat_id,check_vat_id,check_result,checked_at) VALUES ($row[0],'$row[1]','$source_vat_id','$vat_id',9,now()::date)");    
    }
  } 
  else 
  {
    echo "Connection to host not possible, europe.eu down?";
  }
}

echo "Done.";
pg_close($conn);

?>