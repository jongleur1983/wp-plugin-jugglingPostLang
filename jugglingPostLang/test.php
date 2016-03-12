<?php
/**
 * Created by PhpStorm.
 * User: jongl
 * Date: 08.03.2016
 * Time: 23:27
 */

function send_multipart_post_message($url, $fields){
// using WordPress custom functions to retrieve index and apikey
    $eol = "\r\n";
    $data = '';
    $mime_boundary=md5(time());
//
    foreach ($fields as $key => $value) {
        $data .= '--' . $mime_boundary . $eol;
        $data .= 'Content-Disposition: form-data; name="'.$key.'"' . $eol . $eol;
        $data .= $value . $eol;
    }
//    $data .= '--' . $mime_boundary . $eol;
//    $data .= 'Content-Disposition: form-data; name="json"; filename="allposts.json"' . $eol;
//    $data .= 'Content-Type: application/json' . $eol;
//    $data .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
//    $data .= base64_encode($json1) . $eol;
// alternatively use 8bit encoding
//$data .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
//$data .= $json1 . $eol;

    $data .= "--" . $mime_boundary . "--" . $eol . $eol;
    $params = array('http' => array(
        'method' => 'POST',
        'header' => 'Content-Type: multipart/form-data; boundary=' . $mime_boundary,
        'content' => $data
//'proxy' => 'tcp://localhost:8888' //use with Charles to catch http traffic
    ));
    $ctx = stream_context_create($params);
    $response = file_get_contents($url, FILE_TEXT, $ctx);
    return $response;
}

function debug($string) {
    echo '<!-- '.$string." -->\n";
}

/**
 * This function should determine the correct way to wrap a given html snipped to produce valid html.
 * - for a transparent content model element T the function is called recursively to determine the content model of T.
 * - phrasing content is a subset of flow content.
 * - div may contain any flow content and is allowed wherever flow content is allowed.
 * - span may contain phrasing content only and is only allowed where phrasing content is expected.
 *
 * Thus as soon as we find any flow content element that is not phrasing content, we use div;
 * else we use span.
 *
 * @param $content string: the html code we want to wrap in a semantically meaningless element
 * @return string the tag name to wrap the content in a valid way.
 */
function getSurroundingElement($content) {
    $xml = new XMLReader();
    $wrappedContent = "<root>$content</root>";
    $xml->XML($wrappedContent);

    $debugTrace = '';
//    $phrasingContentElements = [
//        'a', 'abbr', 'area', 'audio',
//        'b', 'bdi', 'bdo', 'br', 'button',
//        'canvas', 'cite', 'code',
//        'data', 'datalist', 'del', 'dfn',
//        'em', 'embed',
//        'i', 'iframe', 'img', 'input', 'ins',
//        'kbd', 'keygen',
//        'label', 'link', //(if it is allowed in the body)
//        'map', 'mark', 'math', 'meta' /* (if the itemprop attribute is present) */, 'meter',
//        'noscript',
//        'object', 'output',
//        'picture', 'progress',
//        'q',
//        'ruby',
//        's', 'samp', 'script', 'select', 'small', 'span', 'strong', 'sub', 'sup', 'svg',
//        'template', 'textarea', 'time',
//        'u',
//        'var', 'video',
//        'wbr'
//        // text elements
//    ];

    $notPhrasingFlowContentElements = [
        'address', 'article', 'aside',
        'blockquote',
        'details', 'dialog', 'div', 'dl',
        'fieldset', 'figure', 'footer', 'form',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr',
        'main', 'mark', 'menu', 'meter',
        'nav',
        'p', 'pre',
        'section', 'style',
        'table',
        'ul'
    ];

    $transparentElements = [
        'a',
        'ins',
        'del',
        'object',
        // <video>, <audio> is transparent but does not have any non-transparent content in the context we need here, TODO: check it!
        'map',
        'noscript', // is transparent when scripting is disabled, else it is ignored. Thus we use it as transparent, as that ensures validity.
        'canvas'
    ];

    ob_start(); //"warningCallback");

    $xml->read(); // go to the root node
    $xml->read(); // skip the root node
    do {
        $debugTrace .= $xml->name . '('; //.$read.'#';
        if ($xml->nodeType == XMLReader::ELEMENT) {
            // if element name is a notPhrasingFlowContentElement, we can return div:
            $normalizedName = strtolower($xml->name);
            if (in_array($normalizedName, $transparentElements)) {
                $contentModelFromRecursion = getSurroundingElement($xml->readInnerXml());
                if ($contentModelFromRecursion['errorOccurred']) {
                    return array(
                        'nodeName' => 'span',
                        'trace' => $debugTrace,
                        'errorOccurred' => true);
                } elseif ($contentModelFromRecursion['nodeName'] == 'div') {
                    $debugTrace = $debugTrace . $contentModelFromRecursion['trace'] . ')';
                    return array('nodeName' => 'div',
                        'trace' => $debugTrace);
                }
            } elseif (in_array($normalizedName, $notPhrasingFlowContentElements)) {
                $debugTrace = $debugTrace . ')';
                return array('nodeName' => 'div',
                    'trace' => $debugTrace);
            }
        }
    } while ($read = $xml->next());

    $anyErrorOccurred = !empty(ob_get_clean());
    //ob_end_clean();

    $debugTrace = $debugTrace .')';

    return array(
        'nodeName' => 'span',
        'trace' => $debugTrace,
        'errorOccurred' => $anyErrorOccurred);
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
    $result = getSurroundingElement($item);
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