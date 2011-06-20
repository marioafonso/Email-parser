<?php

class EmailParser
{
    var $_date;
    var $_from;
    var $_to;
    var $_subject;
    var $_body;
    var $_files;

    function __construct($email)
    {
	if (empty($email))
	{
	    throw new Exception("Invalid email argument; Email cannot be empty");
	}

	$this->_files = array();
	$this->_body["plain"] = "";
	$this->_body["html"] = "";

	$this->_parse($email);
    }

    function getParsedData()
    {
        return get_object_vars($this);
    }

    function _parse($str)
    {
	$this->_parseHeader($str);
	$this->_parseContent($str);
    }

    function _parseHeader($str)
    {
	$this->_date = date("Y-m-d H:i:s", strtotime($this->getStringBetween($str, "Date: ", "\n")));
	$aux_subj = trim($this->getStringBetween($str, "Subject:", "\n"));
	$this->_subject = $this->decodeMimeMail($aux_subj);
	if (strlen($this->_subject))
	{
	    $subj_pos = strpos($str, $aux_subj);
	    while (substr($str, $subj_pos+strlen($aux_subj)+1, 1)=="\t" ||
		    substr($str, $subj_pos+strlen($aux_subj)+1, 1)==" ")
	    {
		$start = $subj_pos+strlen($aux_subj)+1;
		$end = strpos($str, "\n", $start);
		$aux_subj_cont = substr($str, $start, $end-$start);
		$this->_subject .= $this->decodeMimeMail(trim($aux_subj_cont));
		$aux_subj .= "\n".$aux_subj_cont;
	    }
	}

	$this->_from = $this->decodeMimeMail($this->getStringBetween($str, "From: ", "\n"));
	$this->_to = $this->decodeMimeMail($this->getStringBetween($str, "\nTo: ", "\n"));
    }

    function _parseContent($str)
    {
        $cType = $this->getStringBetween($str, "Content-Type: ", ";");
	$at = explode("/", $cType);
	switch ($at[0])
	{
	    case "text":
		$cset = $this->getStringBetween($str, "charset=", "\n");
		if (substr($cset, -1, 1)==";") $cset = substr($cset, 0, strlen($cset)-1);
                if (substr($cset, 0, 1) == '"') $cset = substr($cset, 1, strlen($cset)-2);
		$ctre = $this->getStringBetween($str, "Content-Transfer-Encoding: ", "\n");
		$append = "=?".strtoupper($cset)."?Q?";
		$aux_body = $this->getStringBetween($str, "\n\n");
		$end = strpos($aux_body, "\n");
		if ($at[1]=="plain" ||
			$at[1]=="html")
		{
		    if (substr($aux_body, 0, $end)=="This is a multi-part message in MIME format.")
		    {
			$aux_end = strpos($aux_body, "\n", $end+1);
			$bound = substr($aux_body, $end+3, $aux_end-($end+3));
			$ac = $this->_getContent($aux_body, $bound);
			foreach ($ac as $c)
			    $this->_parseContent($c);
			break;
		    }
		    switch ($ctre)
		    {
			case "quoted-printable":
			    $this->_body[$at[1]] = $this->decodeMimeMail($append.$aux_body);
			    break;
			case "7bit":
			    $this->_body[$at[1]] = $this->decodeMimeMail($aux_body);
			    break;
			case "base64":
			    $this->_body[$at[1]] = $this->decodeMimeMail(base64_decode($aux_body));
			    break;
			default:
			    break;
		    }
		    break;
		}
		else
		{
		    $file["name"] = $this->getStringBetween($str, "filename=", "\n");
		    if (substr($file["name"], 0, 1) == '"') $file["name"] = substr($file["name"], 1, strlen($file["name"])-2);
		    switch ($ctre)
		    {
			case "quoted-printable":
			    $file["content"] = $this->decodeMimeMail($append.$aux_body);
			    break;
			case "7bit":
			    $file["content"] = $this->decodeMimeMail($aux_body);
			    break;
			case "base64":
			    $file["content"] = $this->decodeMimeMail(base64_decode($aux_body));
			    break;
			default:
			    break;
		    }
		    array_push($this->_files, $file);
		    break;
		}
	    case "multipart":
	    case "message":
		$bound = $this->getStringBetween($str, "boundary=", "\n");
		if (substr($bound, -1, 1)==";") $bound = substr($bound, 0, strlen($bound)-1);
		if (substr($bound, 0, 1) == '"') $bound = substr($bound, 1, strlen($bound)-2);
		$ac = $this->_getContent($str, $bound);
		foreach ($ac as $c)
		{
		    $this->_parseContent($c);
		}
		break;
            case "image":
	    default:
                $mimeKey = "filename";
                if (!strstr($str, $mimeKey)) $mimeKey = "name";
		$file["name"] = $this->getStringBetween($str, $mimeKey."=", "\n");
		if (substr($file["name"], 0, 1) == '"') $file["name"] = substr($file["name"], 1, strlen($file["name"])-2);
                if (strpos($file["name"], '"')!==false) $file["name"] = strstr($file["name"], '"', true);
		$file["content"] = base64_decode($this->getStringBetween($str, "\n\n"));
		array_push($this->_files, $file);
		break;
	}
    }

    function _getContent($str, $boundary)
    {
	$content = $this->getStringBetween($str, "--".$boundary."\n", "--".$boundary."--\n");
	return explode("--".$boundary."\n", $content);
    }

    function decodeMimeMail($str)
    {
	$str = str_replace("=\n", "", $str);
	$str = str_replace("\n", "[NEWLINE]", $str);
	$str = str_replace("_", " ", $str);
	$str = str_replace("=5F", "_", $str);
	$str = mb_decode_mimeheader($str);
	$str = str_replace("[NEWLINE]", "\n", $str);
	return $str;
    }

    function getStringBetween($str, $str1, $str2="", $caseSensitive=true)
    {
        if (!$caseSensitive)
        {
            $str = strtoupper($str);
            $str1 = strtoupper($str1);
            $str2 = strtoupper($str2);
        }

	$start = strpos($str, $str1)+strlen($str1);
	$length = 0;
	if (strlen($str2))
	{
	    $end = strpos($str, $str2, $start);
	    $length = $end-$start;
	}
	if ($length != 0) return $content = substr($str, $start, $length);
	else return $content = substr($str, $start);
    }
}
