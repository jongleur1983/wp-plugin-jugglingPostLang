<?php
/**
 * Created by PhpStorm.
 * User: jongl
 * Date: 08.03.2016
 * Time: 23:27
 */

function send_multipart_post_message($url, $fields){
    $eol = "\r\n";
    $data = '';
    $mime_boundary=md5(time());

    foreach ($fields as $key => $value) {
        $data .= '--' . $mime_boundary . $eol;
        $data .= 'Content-Disposition: form-data; name="'.$key.'"' . $eol . $eol;
        $data .= $value . $eol;
    }

    $data .= "--" . $mime_boundary . "--" . $eol . $eol;
    $params = array('http' => array(
        'method' => 'POST',
        'header' => 'Content-Type: multipart/form-data; boundary=' . $mime_boundary,
        'content' => $data
    ));
    $ctx = stream_context_create($params);
    $response = file_get_contents($url, FILE_TEXT, $ctx);
    return $response;
}

function debug($string) {
    echo '<!-- '.$string." -->\n";
}

/**
 * Callback called when some error occurs.
 * @param $buffer string the buffer of the output
 * @return string 1, when there's anything in the buffer, empty string otherwise.
 */
function warningCallback($buffer) {
    if (!empty($buffer)) {
        return '123';
    }
    else {
        return '';
    }
}

function getValidationResult($content) {
    // issue an http request, compare with https://github.com/validator/validator/wiki/Service:-Input:-POST-body
    $url = 'http://validator.w3.org/nu/';

    // define the parameters:
    $params = array(
        'out' => 'xml' // xhtml|xml|json|gnu|text; not given = html
        // showsource => yes
        // level => error, else warnings etc. are given as well
    );

    // add parameters to the url
    if (!empty($params)) {
        $url = $url . '?' . http_build_query($params);
    }


    $opts = array('http' =>
        array(
            'method'  => 'POST',
            'header'  => 'Content-type: text/html; charset=utf-8', // post input format
            'content' => $content
        )
    );

    $context  = stream_context_create($opts);

    $result = file_get_contents($url, false, $context);

    return $result;
}

function infoBoxLink($linkText, $content) {
    return '<span class="info">'
        .$linkText
        .'<span class="tooltip">'.$content.'</span>'
        .'</span>';
}

function getInnerXml(SimpleXmlElement $xml, $rootNodeToRemove)
{
    return str_replace(
        array('<'.$rootNodeToRemove.'>', '</'.$rootNodeToRemove.'>'),
        '',
        $xml->saveXML());
}

function analyzeBySeverity($nodes) {
    $issueCount = 0;
    $issueBoxContent = '<ul>';

    if (isset($nodes) && (is_array($nodes->message) || is_object($nodes->message)))
    {
        foreach ($nodes as $item) {
            $issueBoxContent .= '<li>'
                .getInnerXml($item->message, 'message')
                .'</li>';
            $issueCount++;
        }
    }
    $issueBoxContent .= '</ul>';

    return array(
        'issueCount' => $issueCount,
        'issueBoxContent' => $issueBoxContent);
}

function simplePrettyPrintXml($xml) {
    $tags = array_slice(explode('<', $xml), 1);
    $indentation = 0;
    $singleIndent = '  ';
    $result = '';

    foreach ($tags as $tag) {
        $result .= str_repeat($singleIndent, $indentation);
        $result .= '&lt;'.$tag."\n";

        if (substr($tag, 0, 1) == '/') {
            // closing tag:
            $indentation--;
        }
        else {
            // opening tag
            $indentation++;
        }
    }

    return '<pre>'.($result).'</pre>';
}

function analyzeValidationResult($validationXmlResult) {
    $xml = new SimpleXMLElement($validationXmlResult);
    
    $result = infoBoxLink('xml', htmlspecialchars($validationXmlResult));

    $errors = analyzeBySeverity($xml->error);
    if ($errors['issueCount'] > 0)
    {
        $result .= infoBoxLink($errors['issueCount'].' errors', $errors['issueBoxContent']);
    }

    $nonDocErrors = analyzeBySeverity($xml->children('non-document-error'));
    if ($nonDocErrors['issueCount'] > 0) {
        $result .= infoBoxLink($nonDocErrors['issueCount'].' non-doc errors', $nonDocErrors['infoBoxContent']);
    }

    $infos = analyzeBySeverity($xml->info);
    if ($infos['issueCount'] > 0) {
        $result .= infoBoxLink($nonDocErrors['issueCount'].' infos', $infos['infoBoxContent']);
    }

    return array(
        'errors' => $errors['issueCount'],
        'non-document-errors' => $nonDocErrors['issueCount'],
        'infos' => $infos['issueCount'],
        'cellContent' => $result
    );
}

function completeHtml($testPart) {
    return '<!DOCTYPE html>'
        .'<html>'
        .'<head>'
        .'<title>ValidateTest</title>'
        .'</head>'
        .'<body>'
        .$testPart
        .'</body>'
        .'</html>';
}

error_reporting(E_ALL | E_NOTICE);

?>
<!DOCTYPE html>
<html>
    <head>
        <title>Testscript</title>
        <style type="text/css">
            .info {
                margin:5px;
                color:blue;
                text-decoration: underline;
            }
            .info:hover .tooltip {
                display:block;
            }

            .tooltip {
                color: black;
                border: 1px solid black;
                display: none;
                max-width: 400pt;
                max-height: 400pt;
                position: absolute;
                text-align: left;
                background: #FFFFCC;
                overflow:auto;
            }

            td, th {
                padding: 3pt;
            }

            td.success {
                background-color:forestgreen;
                color:white;
                text-align:center;
            }
            td.fail {
                background-color: red;
                color:white;
                text-align:center;
            }
        </style>
    </head>
    <body>
        <table border="black" cellpadding="0" cellspacing="0">
            <tr>
                <th>input</th>
                <th>function trace</th>
                <th>output</th>
                <th>validator output</th>
                <th>expected result</th>
                <th>success</th>
            </tr>
<?php
// mock wordpress functionality:
function add_action($string, $array, $int1 = 0, $int2 = 1) {
    // do nothing
}

function add_filter($string, $array) {
    // do nothing
}

// include and instantiate plugin class:
require_once 'jugglingPostLang.php';
$jugglingPostLang =  new JugglingPostLang();

// test the function:
foreach ([
             '<html/>' => false,
             ' ' => true,
             '<span/>' => false,
             '<div><span/></div>' => false,
             '<a href="http://www.jugglingsource.de">a <strong>strong</strong> link</a>' => true,
             '<h1>A heading</h1><div class="author"><span class="name">jongleur</span></div>' => true,
             '<span>A span is not allowed to contain a <div>div</div></span>' => false,
             // TODO invalid xml produces warning in this script yet:
             '<h2>some<p>wrongly Tagged </h2> html code</p>' => false
         ]  as $item => $expected) {
    echo "<tr>";

    // input:
    echo '<td>'.simplePrettyPrintXml($item).'</td>';

    // trace:
    $result = $jugglingPostLang->getSurroundingElement($item);
    echo '<td>'.$result['trace'].'</td>';

    // output (result)
    echo '<td>'.htmlspecialchars($result['nodeName']).'</td>';

    // analysis:
    $response = getValidationResult(completeHtml($item));
    $analyzed = analyzeValidationResult($response);

    $isValid = ($analyzed['errors'] + $analyzed['non-document-errors'] + $analyzed['infos']) == 0;
    $messages = $analyzed['cellContent'];
    echo '<td>'.$messages.'</td>';
    // expected result:
    echo '<td class="'.($expected ? 'expect-valid' : 'expect-invalid').'">'
            .($expected ? 'valid' : 'invalid')
        .'</td>';
    // real results and whether they match:
    echo '<td class="'.($expected == $isValid ? 'success' : 'fail').'">'
            .($expected == $isValid ? 'ok' : 'fail')
        .'</td>';

    echo '</tr>';
}
?>
</table>

</body>
</html>
<?php
// space führt zu exception beim read(),
