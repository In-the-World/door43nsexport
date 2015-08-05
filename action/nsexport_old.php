<?php
/**
 * Name: nsexport.php
 * Description: A Dokuwiki action plugin to generate the html for multiple pages for exporting
 *
 * Author: Richard Mahn
 * Date:   2015-08-04
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

// $door43shared is a global instance, and can be used by any of the door43 plugins
if (empty($door43shared)) {
    $door43shared = plugin_load('helper', 'door43shared');
}

/* @var $door43shared helper_plugin_door43shared */
$door43shared->loadAjaxHelper();

class action_plugin_door43nsexport_nsexport_old extends DokuWiki_Action_Plugin {

    protected $rootId = null;
    protected $sections = array();
    protected $pages = array();
    protected $links = array();
    protected $tmpFiles = array();

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_export_action');
    }

    /**
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_export_action(Doku_Event &$event, /** @noinspection PhpUnusedParameterInspection */ $param)
    {
        return;
        if ($event->data !== 'nsexport') return;

        echo '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>translationAcademy</title>
    <style type="text/css">
        * { font-family: Calibri, Helvetica, Arial, sans-serif; font-size: 12pt; }
        h1, h2, h3, h4, h5 { font-weight: 700; }
        h1 { font-size: 20pt; }
        h2 { font-size: 18pt; }
        h3 { font-size: 16pt; }
        h4 { font-size: 14pt; }
        h5 { font-size: 13pt; }
        p { margin-bottom: 2em; }
        div.page { page-break-after: always; }
    </style>
</head>
<body>
';

        $this->rootId = getID();

        $this->transverse_pages($this->rootId);
        ksort($this->sections);

#        print_r($this->sections);
#        print_r($this->links);

        $urls = array_keys($this->links);
        $anchors = array_values($this->links);
        foreach($this->sections as $sectionId=>$pageIds) {
            sort($pageIds);

            $topPageIds = array($sectionId, "$sectionId:home", "$sectionId:index", "$sectionId:toc"); // Keep these on top in this order
            foreach(array_reverse($topPageIds) as $searchPageId) {
                $index = array_search($searchPageId, $pageIds, true);
                if ($index > 0) {
                    $pageId = $pageIds[$index];
                    unset($pageIds[$index]);
                    array_unshift($pageIds, $pageId);
                }
            }

#            echo "<hr/><h1>SECTION: $sectionId</h1><hr/>\n";
            foreach ($pageIds as $pageId) {
#                echo "<hr/><h2>PAGE: $pageId</h2><hr/>\n";

                if (isset($this->pages[$pageId]) && $this->pages[$pageId]) {
                    $content = $this->pages[$pageId];
                    $content = preg_replace('/<(\\{0,1})h1/', '<$1h2', $content);
                    if($pageId == $pageIds[0]){
                        $content = preg_replace('/<h\d(.*?)<\/h\d/', '<h1$1</h1', $content, 1);
                    }
                    $content = str_replace($urls, $anchors, $content);
                    echo $content;
                }
            }
        }

        echo '
</body>
</html>
';

        exit;
    }

    protected function transverse_pages($id, $is_page = true)
    {
        $filename = wikiFN($id);

        if( ! file_exists($filename) )
            return;

#        echo "<br/>BEFORE: $id<br/>";
        $subId = ltrim(substr($id, strlen($this->rootId)), ':');
#        echo "AFTER: $subId<br/>";

        if(isset($this->pages[$subId]))
            return;

#        echo "PROCESSING: $id<BR/>\n";

        if(substr_count($subId, ':') <= 1) {
            $subdir = pathinfo($filename, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_BASENAME);

            if (file_exists($subdir) && is_dir($subdir)) {
                $sectionId = $subId;
            } else {
                $sectionId = preg_replace('/:[^:]+$/', '', $subId);
            }
        }
        else {
            $sectionId = preg_replace('/:.*$/', '', $subId);
        }

        if(! isset($this->sections[$sectionId])){
            $this->sections[$sectionId] = array();
        }

        $this->sections[$sectionId][] = $subId;

        $xhtml = p_wiki_xhtml($id);

        // Remove links to pad.door43.org
        $xhtml = preg_replace('/.*https:\/\/pad\.door43.*\n/', '', $xhtml);
        // Remove blog of tags
        $xhtml = preg_replace('/<div class="tags"><span>\n.*\n<\/span><\/div>\n/s', '', $xhtml);
        // Remove epady links
        $xhtml = preg_replace('/.*publish-epady.*\n/', '', $xhtml);
        // Remove empty paragraphs
        $xhtml = preg_replace('/\n<p>\n<\/p>/', '', $xhtml);
        // Remove hr's
        $xhtml = preg_replace('/\n<hr\s*\/>/', '', $xhtml);

        if( $is_page ) {
            $this->pages[$subId] = $xhtml;
        }
        else {
            $this->pages[$subId] = '';
        }

        preg_match('/<h\d.*? id="(.*?)"/', $xhtml, $match);
        if($match){
            $this->links['"/'.str_replace(':', '/', $id).'"'] = '#'.$match[1];
        }

        $content = file_get_contents($filename);
        $includes = $this->get_includes($content);
        $links = $this->get_links($content);

        $rootId = getID();

#        echo "<BR/>$id - GETTING INCLUDES ===><BR/>\n";
        foreach($includes as $include){
            if(strpos($include, $rootId) === 0){
#                echo "$id - GOING TO PROCESS INCLUDE $include ----><BR/>\n";
                $this->transverse_pages($include, false);
#                echo "$id - <---- DONE PROCESSING INCLUDE $include<BR/>\n";
            }
        }
#        echo "$id - <==== DONE GETTING INCLUDES<BR/>\n";

#        echo "<BR/>$id - GETTING LINKS ===><BR/>\n";
        foreach($links as $link){
            if(strpos($link, $rootId) === 0){
#                echo "$id - GOING TO PROCESS LINK $link ----><BR/>\n";
                $this->transverse_pages($link);
#                echo "$id - <---- GOING TO PROCESS LINK $link<BR/>\n";
            }
        }
#        echo "$id - <==== DONE GETTING LINKS<BR/>\n";
    }

    public static function get_links($str){
        preg_match_all("/\\[\\[:{0,1}(.*?)\\|/", $str, $matches);

        if($matches && count($matches) > 1) {
            return $matches[1];
        }
        else {
            return array();
        }
    }

    public static function get_includes($str){
        preg_match_all("/\\{\\{page>(.*?)\\}/", $str, $matches);

        if($matches && count($matches) > 1) {
            return $matches[1];
        }
        else {
            return array();
        }
    }
}
