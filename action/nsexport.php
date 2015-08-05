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

class action_plugin_door43nsexport_nsexport extends DokuWiki_Action_Plugin {

    protected $rootId = null;
    protected $tree = array(
        'nodes'=>array(),
        'nodeOrder'=>array(),
    );
    protected $links = array();

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

        $this->populate_tree($this->rootId);
        $this->output_tree($this->tree['nodes'][''], 1);

        echo '
</body>
</html>
';

        exit;
    }

    protected function populate_tree($id)
    {
#        echo "$id - PROCESSING<BR/>\n";

        $filename = wikiFN($id);

        if( ! file_exists($filename) )
            return;

        $subId = preg_replace('/^'.$this->rootId.'/', '', $id);

        $node = &$this->tree;
        $fullNodeId = $this->rootId;
        foreach(explode(':', $subId) as $nodeId){
            $parentNode = &$node;
            $fullNodeId = $fullNodeId.($nodeId?':':'').$nodeId;
            if( ! isset($parentNode['nodes'][$nodeId])){
                $parentNode['nodes'][$nodeId] = array(
                    'id'=>$fullNodeId,
                    'nodes'=>array(),
                    'nodeOrder'=>array(),
                );
            }

            $node = &$parentNode['nodes'][$nodeId];

            if(! in_array($nodeId, $parentNode['nodeOrder'])) {
                $parentNode['nodeOrder'][] = $nodeId;
            }
        }

        if(isset($node['content']))
            return;

        $xhtml = p_wiki_xhtml($id);

        $node['content'] = $xhtml;
// if(preg_match('/(<h\d[ >].+?<\/h\d>)/', $xhtml, $matches)) {
// $node['content'] = $matches[1];
// }

        preg_match('/<h\d[^>]*? id="(.*?)"/', $xhtml, $match);
        if($match){
            $this->links['"/'.str_replace(':', '/', $id).'"'] = '"#'.$match[1].'"';
            $this->links['"/'.str_replace(':', '/', $id).'#'] = '"#';
        }

        $node['links'] = $this->get_links($xhtml);

        # first add all links that are children of this node to the nodeOrder array
        foreach($node['links'] as $link) {
            if($link) {
                $linkId = str_replace('/', ':', $link);
                if (preg_match('/^'.$subId.($subId?':':'').'([^:]+)$/', $linkId, $matches)) {
                    $node['nodeOrder'][] = $matches[1];
                }
            }
        }

        # now populate the tree with each link
        foreach($node['links'] as $link) {
            if($link){
                $linkId = str_replace('/', ':', $link);
                $this->populate_tree($this->rootId . ':' . $linkId);
            }
        }
    }

    public function get_links($content){
        preg_match_all('/href=[\'"]\/'.str_replace(':','\/',$this->rootId).'\/(.*?)[#\'"]/', $content, $matches);

        if($matches && count($matches) > 1) {
            return $matches[1];
        }
        else {
            return array();
        }
    }

    public function output_tree(&$node, $level = 1){
        if(! $node)
            return;

        if(isset($node['content'])) {
            $content = $node['content'];
            for ($i = 1; $i <= 3; ++$i) {
                $content = preg_replace('/<h' . $i . '([ >].+?)<\/h' . $i . '>/', '<h' . ($i + 1) . '$1</h' . ($i + 1) . '>', $content, 1);
            }
            $content = preg_replace('/<h\d([ >].+?)<\/h\d>/', '<h' . $level . '$1</h' . $level . '>', $content, 1);

            // Remove links to pad.door43.org
            $content = preg_replace('/.*https:\/\/pad\.door43.*\n/', '', $content);
            // Remove blog of tags
            $content = preg_replace('/<div class="tags"><span>\n.*\n<\/span><\/div>\n/s', '', $content);
            // Remove epady links
            $content = preg_replace('/.*publish-epady.*\n/', '', $content);
            // Remove empty paragraphs
            $content = preg_replace('/\n<p>\n<\/p>/', '', $content);
            // Remove hr's
            $content = preg_replace('/\n<hr\s*\/>/', '', $content);

            // Change all links that have content in this export to use their id
            $urls = array_keys($this->links);
            $anchors = array_values($this->links);
            $content = str_replace($urls, $anchors, $content);

            # make sure all other links that are local from root are given an absolute URL
#            $content = preg_replace('/(href|src)=([\'"])\//i', '$1=$2http://'.$_SERVER['SERVER_NAME'].'/', $content);

            echo $content;
        }

        if(isset($node['nodes']) && count($node['nodes'])) {
            $nodes = $node['nodes'];

            if (isset($node['nodeOrder']) && $node['nodeOrder']) {
                foreach ($node['nodeOrder'] as $nodeId) {
                    if(isset($nodes[$nodeId])) {
                        $this->output_tree($nodes[$nodeId], ($level + 1));
                        unset($nodes[$nodeId]);
                    }
                }
            }
            if (count($nodes)) {
                foreach ($nodes as $subnode) {
                    $this->output_tree($subnode, ($level + 1));
                }
            }
        }
    }
}
