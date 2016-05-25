<?php

namespace FwsDevelopment;

use Carbon\Carbon;
use Soapclient, SoapVar;

Class Yuki
{
	private $session_id;

	private $administrationId;

	public function __construct()
	{
		if(is_null($this->administrationId))
		{
			$this->administrationId = config('yuki.administrationID');
		}
	}

	public function connect ($service, $administration = null)
	{
		$url = NULL;

		switch($service)
		{
			case 'accounting':
				$url = "https://api.yukiworks.nl/ws/Accounting.asmx?WSDL";
				break;
			case 'sales':
				$url = "https://api.yukiworks.nl/ws/Sales.asmx?WSDL";
				break;
			default:
				$url = "https://api.yukiworks.nl/ws/Accounting.asmx?WSDL";
				break;
		}

		try
		{
			$soap = new Soapclient($url);
			$res = $soap->Authenticate(['accessKey' => config('yuki.access_key')]);
			$this->session_id = $res->AuthenticateResult;

			return $soap;
		}
		catch (SoapFault $result)
		{
			return $result;
		}
	}

	private function makeJournalEntrys($entrys)
	{
		$xml = "";

		foreach ($entrys as $entry)
		{
			$xml .= "
				<JournalEntry>
					<ContactName>" . $entry['name'] . "</ContactName>
					<EntryDate>" . $entry['date'] . "</EntryDate>
					<GLAccount>" . $entry['gla'] . "</GLAccount>
					<Amount>" . $entry['amount'] . "</Amount>
					<Description>" . $entry['description'] . "</Description>
				</JournalEntry>
			";
		}

		return $xml;
	}

	public function insertJournal($journals, $subject)
	{
		try {
			$soap = $this->connect('accounting');

			$xml = '
					<Journal xmlns="urn:xmlns:http://www.theyukicompany.com:journal" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
						<AdministrationID>'.$this->administrationId.'</AdministrationID>
						<DocumentSubject>'.$subject.'</DocumentSubject>
						<JournalType>GeneralJournal</JournalType>
							' . $this->makeJournalEntrys($journals) . '
					</Journal>';

			$xmlVar = new SoapVar('<ns1:xmlDoc>'.$xml.'</ns1:xmlDoc>', XSD_ANYXML);
			$result = $soap->ProcessJournal(['sessionID' => $this->session_id, 'administrationID' => $this->administrationId, 'xmlDoc' => $xmlVar]);

			return $result->ProcessJournalResult;
		}
		catch(SoapFault $fault)
		{
			return $fault->faultstring;
		}
	}

	public function uploadFile($files, $ordner)
	{
		// Validate the category
		$available_ordners = [0,1,2,3,4,5,6,7,8,100,101,102];

		if (!in_array($ordnet, $available_ordners))
		{
			return false;
		}
		try {
			$url = "https://api.yukiworks.nl/docs/Upload.aspx" . '?WebServiceAccessKey=' . config('accessKey') . '&Adimnistration='. $this->administrationId . '&FileName=' . urlencode($file->getFileName().'.pdf');

			$params = [
				'http' => [
					'method' => 'POST',
					'header' => 'Content-Length: ' . $file->getFileSize(),
					'content' => file_get_contents($file)
				]
			];

			$ctx = stream_context_create($params);
			$fp = fopen($url, 'rb', false, $ctx);

			return @stream_get_contents($fp);
		}
		catch (Exeption $e)
		{
			return $e;
		}

	}

}