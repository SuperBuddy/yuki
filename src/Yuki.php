<?php

namespace FwsDevelopment;

use Carbon\Carbon;
use Soapclient, SoapVar;
use File;

Class Yuki
{
	private $session_id;

	private $administrationId;

	/**
     * @author Frank Wichers Schreur <frank@superbuddy.nl>
     *
     * Sets the administration id.
     *
     */
	public function __construct()
	{
		if (is_null($this->administrationId))
		{
			$this->administrationId = config('yuki.administrationID');
		}
	}

	/**
     * @author Frank Wichers Schreur <frank@superbuddy.nl>
     *
     * Connects to the Yuki api.
     *
     * @param $service = the serivice you want to connect to.
     * @param $administration = if left null. The current administration.
     *
     * @return Soap object
     */
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

	/**
     * @author Frank Wichers Schreur <frank@superbuddy.nl>
     *
     * Makes the journal entry's for memorial booking's.
     *
     * @param Array $entrys
     *
     * @return xml string
     */
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

	/**
     * @author Frank Wichers Schreur <frank@superbuddy.nl>
     *
     * Makes the memorial bookings.
     *
     * @param Array $journals
     * @param String $subject
     *
     * @return Soap result.
     */
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

	/**
     * @author Frank Wichers Schreur <frank@superbuddy.nl>
     *
     * Uploads the file to Yuki
     *
     * @param Files $file
     * @param Integer $ordner
     *
     * @return Response
     */
	public function uploadFile($file, $ordner)
	{
		// Validate the category
		$available_ordners = [0,1,2,3,4,5,6,7,8,100,101,102];

		if (!in_array($ordner, $available_ordners))
		{
			return false;
		}

		try {
			$url = "https://api.yukiworks.nl/docs/Upload.aspx" . '?WebServiceAccessKey=' . config('yuki.access_key') . '&Administration='. $this->administrationId . '&FileName=' . urlencode($file->getFileName().".".$file->getExtension());

			$file_content = file_get_contents($file->getRealPath());

			$type = File::mimeType($file->getRealPath());

			$params = [
				'http' => [
					'method' => 'POST',
					'header' => 'Content-Type: ' . $type."\r\n".
								'Content-Length: ' . $file->getSize(),
					'content' => $file_content
				]
			];

			$ctx = stream_context_create($params);
			$fp = fopen($url, 'rb', false, $ctx);

			$response = @stream_get_contents($fp);
		}
		catch (Exeption $e)
		{
			return $e;
		}

		return $response;
	}
}