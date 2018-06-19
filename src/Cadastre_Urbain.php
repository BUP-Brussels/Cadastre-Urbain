<?php 
class Cadastre_Urbain
{
	const GEOSERVER_URBIS_ADM = "//geoservices-urbis.irisnet.be/geoserver/UrbisAdm/wms";
	const GEOSERVER_BRUGIS = "//mybrugis.irisnet.be/geoserver/wms";
	const GEOSERVER_NOVA = "//geoservices-others.irisnet.be/geoserver/Nova/ows";
	const TIMEOUT = 10;	
	
	public static function setPermitsNova($cql_filter = null, $limit = null)
	{
		$cql_filter = $cql_filter ?? "TYPEDOSSIER IN ('PFD', 'PFU')";
		$limit 		= $limit ?? 100;
		$orderBy 	= 'S_IDDOSSIER D';
		
		$url = self::GEOSERVER_NOVA;
		$fields = array(
			'service' => 'WFS',
			'version' => '2.0.0',
			'request' => 'GetFeature',
			'typeName' => 'Nova:VMNOVASPRBVIEW',
			'srsName' => 'EPSG:31370',
			'outputFormat' => 'json',
			'cql_filter' => $cql_filter,
			'sortBy' => $orderBy,
			'count' => $limit
		);
		$client = new GuzzleHttp\Client();
		
		try
		{
			$response = $client->request('GET', $url . "?" . http_build_query($fields) , ['timeout' => self::TIMEOUT]);
			$json = json_decode((string)$response->getBody());
		}
		catch(Exception $e)
		{
			return;
		}

		$nb = isset($json->features) ? count($json->features) : 0;
		for ($i = 0; $i < $nb; $i++)
		{
			$data[$i]['REFNOVA'] = $json->features[$i]->properties->REFNOVA;
			$exp = explode("/", $data[$i]['REFNOVA']);
			$data[$i]['DOCREF'] = trim($exp[count($exp) - 1]);
			$data[$i]['REFERENCESPECIFIQUE'] = $json->features[$i]->properties->REFERENCESPECIFIQUE;
			$data[$i]['STREETNAMEFR'] = $json->features[$i]->properties->STREETNAMEFR;
			$data[$i]['STREETNAMENL'] = $json->features[$i]->properties->STREETNAMENL;
			$data[$i]['TYPEDOSSIER'] = $json->features[$i]->properties->TYPEDOSSIER;
			$data[$i]['OBJECTFR'] = $json->features[$i]->properties->OBJECTFR;
			$data[$i]['OBJECTNL'] = $json->features[$i]->properties->OBJECTNL;
			$data[$i]['S_IDDOSSIER'] = $json->features[$i]->properties->S_IDDOSSIER;
			$data[$i]['RI'] = $json->features[$i]->properties->RI;
			$data[$i]['EI'] = $json->features[$i]->properties->EI;
			$data[$i]['ZIPCODE'] = $json->features[$i]->properties->ZIPCODE;
			$data[$i]['STATUT'] = $json->features[$i]->properties->STATUTPERMISFR;
			$data[$i]['LOCKTIME'] = $json->features[$i]->properties->LOCKTIME ? DateTime::createFromFormat('Y-m-d', substr($json->features[$i]->properties->LOCKTIME,0,-1))->format('d-m-y') : null;
			
			$data[$i]['DATENOTIFDECISION'] = $json->features[$i]->properties->DATENOTIFDECISION ? DateTime::createFromFormat('d/m/Y', $json->features[$i]->properties->DATENOTIFDECISION)->format('d-m-y') : null;
			$data[$i]['DATEARDOSSCOMPLET'] = $json->features[$i]->properties->DATEARDOSSCOMPLET ? DateTime::createFromFormat('d/m/Y', $json->features[$i]->properties->DATEARDOSSCOMPLET)->format('d-m-y') : null;
			
			// Ranges de Numéros de police
			$data[$i]['NUMBER_RANGE'] = $json->features[$i]->properties->NUMBERPARTFROM;
			if (!is_null($json->features[$i]->properties->NUMBERPARTTO))
			{
				$data[$i]['NUMBER_RANGE'].= "-".$json->features[$i]->properties->NUMBERPARTTO;
			};
			$data[$i]['WFS'] = self::GEOSERVER_NOVA . "?service=WFS&version=2.0.0&request=GetFeature&typeName=Nova:VMNOVASPRBVIEW&outputFormat=json&cql_FILTER=S_IDDOSSIER=%27" . $data[$i]['S_IDDOSSIER'] . "%27";
			$data[$i]['WFS_LIEN'] = "<a href=\"" . $data[$i]['WFS'] . "\">Nova</a>";
		};
		return $data ?? null;
	}

	public static function setPermitsMercator(string $user, string $password, string $ref = null)
	{
		$url = self::GEOSERVER_BRUGIS;
		$fields = array(
			'service' => 'WFS',
			'version' => '2.0.0',
			'request' => 'GetFeature',
			'typeName' => 'URBANALYSIS:PUBREPERAGES',
			'srsName' => 'EPSG:31370',
			'outputFormat' => 'json',
			'cql_filter' => "DOCREF LIKE '%" . $ref . "'",
		);
		$client = new GuzzleHttp\Client();
		
		try
		{
			$response = $client->request('GET', $url . "?" . http_build_query($fields) , ['timeout' => self::TIMEOUT, 'auth' => [$user, $password]]);
			$json = json_decode((string)$response->getBody());
		}
		catch(Exception $e)
		{
			return 'timeout';
		}

		$nb = isset($json->features) ? count($json->features) : 0;
		for ($i = 0; $i < $nb; $i++)
		{
			$data['DOCREF'] = $json->features[$i]->properties->DOCREF;
			$data['STATE'] = $json->features[$i]->properties->STATE;
			$data['DOCPATH_FR'] = $json->features[$i]->properties->DOCPATH_FR;
			$data['DOCPATH_NL'] = $json->features[$i]->properties->DOCPATH_NL;
			$data['ID'] = $json->features[$i]->properties->ID;
			$data['WFS'] = $url . "?" . http_build_query($fields);
			$data['WFS_LIEN'] = "<a href=\"" . $data['WFS'] . "\">Mercator</a>";
			$geomBuilding = geoPHP::load($json, 'json');
			$data['WKT'] = $geomBuilding->out('wkt');
		};
		
		return $data ?? null;
	}

	public static function setParcels(string $geom = null, $crs_out = 31370, $buffer = "-0.9")
	{
		if (is_null($geom))
		{
			return null;
		};
		if (!is_null($buffer))
		{
			$geom = "buffer(" . $geom . "," . $buffer . ")";
		};

		$url = self::GEOSERVER_URBIS_ADM;
		$fields = array(
			'service' => 'WFS',
			'version' => '2.0.0',
			'request' => 'GetFeature',
			'typeName' => 'UrbisAdm:Capa',
			'srsName' => 'EPSG:' . $crs_out,
			'outputFormat' => 'json',
			'cql_filter' => "INTERSECTS(GEOM, " . $geom . ")"
		);
		$client = new GuzzleHttp\Client();
		
		try
		{
			$response = $client->request('POST', $url, ['form_params' => $fields, 'timeout' => 10]);
			$json = json_decode((string)$response->getBody());
		}
		catch(Exception $e)
		{
			return;
		}

		if (!isset($json->features[0]->geometry))
		{
			return null;
		};
		
		$nb = isset($json->features) ? count($json->features) : 0;
		for ($i = 0; $i < $nb; $i++)
		{
			$data[$i]['CAPA_INSPIRE_ID'] = $json->features[$i]->properties->CAPA_INSPIRE_ID ?? null;
			$data[$i]['CAPA_APNC_MAPC'] = $json->features[$i]->properties->CAPA_APNC_MAPC ?? null;
			$data[$i]['CAPA_EXPONENT_NUM'] = $json->features[$i]->properties->CAPA_EXPONENT_NUM ?? null;
			$data[$i]['CAPA_EXPONENT_ALPHA'] = $json->features[$i]->properties->CAPA_EXPONENT_ALPHA ?? null;
			$data[$i]['CAPA_RADICAL_NUM'] = $json->features[$i]->properties->CAPA_RADICAL_NUM ?? null;
			$data[$i]['CAPA_CAPAKEY'] = $json->features[$i]->properties->CAPA_CAPAKEY ?? null;
			$data[$i]['CAPA_CD5C'] = $json->features[$i]->properties->CAPA_CD5C ?? null;
			$data[$i]['CAPA_CSNC'] = $json->features[$i]->properties->CAPA_CSNC ?? null;
			$data[$i]['CAPA_APNC_CADC'] = $json->features[$i]->properties->CAPA_APNC_CADC ?? null;
			$data[$i]['WFS'] = self::GEOSERVER_URBIS_ADM . "?service=WFS&version=2.0.0&request=GetFeature&typeName=UrbisAdm%3ACapa&outputFormat=json&cql_filter=CAPA_INSPIRE_ID='" . $data[$i]['CAPA_INSPIRE_ID'] . "'";
			$data[$i]['WFS_LIEN'] = "<a href=\"" . $data[$i]['WFS'] . "\">Parcelle</a>";
		};
		
		return $data;
	}
	
		public static function setPWNC(string $streetname, int $zipcode)
	{
		$url = self::GEOSERVER_URBIS_ADM;
		$fields = array(
			'service' => 'WFS',
			'version' => '2.0.0',
			'request' => 'GetFeature',
			'typeName' => 'UrbisAdm:Pw',
			'srsName' => 'EPSG:31370',
			'outputFormat' => 'json',
			'propertyname' => 'PN_PNMC,MZ_NATIONAL_CODE,PN_NAME_FRE,PN_NAME_DUT',
			'cql_filter' => "(PN_NAME_FRE='".str_replace("'", "''", $streetname)."' AND MZ_NATIONAL_CODE='".$zipcode."')"
		);
		$client = new GuzzleHttp\Client();
		
		try
		{
			$response = $client->request('GET', $url . "?" . http_build_query($fields) , ['timeout' => self::TIMEOUT]);
			$json = json_decode((string)$response->getBody());
		}
		catch(Exception $e)
		{
			return 'timeout';
		}


		$nb = isset($json->features) ? count($json->features) : 0;
		$data = $json->features[0]->properties->MZ_NATIONAL_CODE.$json->features[0]->properties->PN_PNMC;

		return $data ?? null;
	}
};

