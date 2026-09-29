<?php
// Editrail extension, https://github.com/pfadfinder26/yellow-editrail
// Based on Datenstrom Yellow, https://datenstrom.se/yellow/

class YellowEditrail {
    const VERSION = "0.1.2";
    const PRIORITY = 16;    // before the edit extension, it answers every request under /edit/
    public $yellow;         // access to API
    public $number;         // number of the page in the tree

    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->language->setDefault("EditrailPages", "Pages", "en");
        $this->yellow->language->setDefault("EditrailPages", "Seiten", "de");
        $this->yellow->language->setDefault("EditrailExit", "Leave editing", "en");
        $this->yellow->language->setDefault("EditrailExit", "Bearbeiten beenden", "de");
        $this->yellow->language->setDefault("EditrailCreate", "New page", "en");
        $this->yellow->language->setDefault("EditrailCreate", "Neue Seite", "de");
        $this->yellow->language->setDefault("EditrailDelete", "Delete page", "en");
        $this->yellow->language->setDefault("EditrailDelete", "Seite löschen", "de");
        $this->yellow->language->setDefault("EditrailEditPage", "Edit page", "en");
        $this->yellow->language->setDefault("EditrailEditPage", "Seite bearbeiten", "de");
        $this->yellow->language->setDefault("EditrailHidePage", "Hide page", "en");
        $this->yellow->language->setDefault("EditrailHidePage", "Seite verstecken", "de");
        $this->yellow->language->setDefault("EditrailShowPage", "Show page", "en");
        $this->yellow->language->setDefault("EditrailShowPage", "Seite zeigen", "de");
        $this->yellow->language->setDefault("EditrailMedia", "Media", "en");
        $this->yellow->language->setDefault("EditrailMedia", "Medien", "de");
        $this->yellow->language->setDefault("EditrailUpload", "Add files", "en");
        $this->yellow->language->setDefault("EditrailUpload", "Dateien hinzufügen", "de");
        $this->yellow->language->setDefault("EditrailInsertFile", "Insert into the page", "en");
        $this->yellow->language->setDefault("EditrailInsertFile", "In die Seite einfügen", "de");
        $this->yellow->language->setDefault("EditrailDeleteFile", "Delete file", "en");
        $this->yellow->language->setDefault("EditrailDeleteFile", "Datei löschen", "de");
    }

    // Handle page extra data, the style and the script of the rail
    public function onParsePageExtra($page, $name) {
        if ($name=="header" && $this->isEditable()) {
            $assetLocation = $this->yellow->system->get("coreServerBase").
                $this->yellow->system->get("coreAssetLocation");
            return "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$assetLocation}editrail.css\" />\n".
                "<script type=\"text/javascript\" defer=\"defer\" src=\"{$assetLocation}editrail.js\"></script>\n";
        }
        if ($name=="footer" && $this->isEditable()) return $this->getRailHtml($page);
        return null;
    }

    // Return the address of a media file with the time it was changed
    public function getMediaUrl($location) {
        if (is_string_empty($location)) return "";
        $base = $this->yellow->system->get("coreServerBase");
        $path = substru($location, 0, strlenu($base))==$base ? substru($location, strlenu($base)) : $location;
        $fileName = ltrim($path, "/");
        if (!is_file($fileName)) return $location;
        return $location."?v=".filemtime($fileName);
    }

    // Handle request, an editor deletes a file of the media directory
    public function onRequest($scheme, $address, $base, $location, $fileName) {
        if ($this->yellow->toolbox->getServer("REQUEST_METHOD")!="POST") return 0;
        $media = trim($this->yellow->page->getRequest("editrail-media-delete"));
        if (is_string_empty($media)) return 0;
        if (!$this->isEditor($scheme, $address, $base, $location, $fileName)) {
            return $this->yellow->sendStatus(403);
        }
        $fileNameMedia = $this->getMediaFileName($media);
        if (is_string_empty($fileNameMedia) || !is_file($fileNameMedia)) return $this->yellow->sendStatus(404);
        if (!$this->yellow->toolbox->deleteFile($fileNameMedia, $this->yellow->system->get("coreTrashDirectory"))) {
            return $this->yellow->sendStatus(500);
        }
        return $this->yellow->sendStatus(303, $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location));
    }

    // Check if the one who asks may change the files, the edit extension knows
    public function isEditor($scheme, $address, $base, $location, $fileName) {
        if (!$this->yellow->extension->isExisting("edit")) return false;
        $edit = $this->yellow->extension->get("edit");
        // this runs before the edit extension, which would answer the request itself, so the
        // login is checked here, with the same method the edit extension uses
        if (!$edit->checkUserAuth($scheme, $address, $base, $location, $fileName)) return false;
        if (!$edit->response->isUserAccess("upload", $this->getLocationPage($location))) return false;
        $tokenExpected = $this->yellow->toolbox->getCookie("yellowcsrftoken");
        $tokenReceived = $this->yellow->page->getRequest("yellowcsrftoken");
        return !is_string_empty($tokenExpected) &&
            $this->yellow->toolbox->verifyToken($tokenExpected, $tokenReceived);
    }

    // Return the file name of a media file, empty for anything outside the images
    public function getMediaFileName($location) {
        $base = $this->yellow->system->get("coreServerBase");
        if (substru($location, 0, strlenu($base))==$base) $location = substru($location, strlenu($base));
        $images = $this->yellow->system->get("coreImageLocation");
        if (substru($location, 0, strlenu($images))!=$images) return "";
        if (strposu($location, "..")!==false) return "";
        $fileName = $this->yellow->system->get("coreMediaDirectory")."images/".
            substru($location, strlenu($images));
        return $this->yellow->lookup->isFileLocation($location) ? $fileName : "";
    }

    // Return the rail with the editing buttons, the page tree and the files
    public function getRailHtml($page) {
        $this->number = 0;
        $output = "<div class=\"editrail\" id=\"editrail\"".
            " data-label-create=\"".$this->yellow->language->getTextHtml("editrailCreate")."\"".
            " data-label-delete=\"".$this->yellow->language->getTextHtml("editrailDelete")."\">\n";
        $output .= "<input class=\"editrail-toggle\" type=\"checkbox\" id=\"editrail-toggle\" />\n";
        $output .= "<label class=\"editrail-item editrail-expand\" for=\"editrail-toggle\">".
            $this->yellow->language->getTextHtml("editrailPages")."</label>\n";
        $output .= "<div class=\"editrail-actions\"></div>\n";
        $output .= "<div class=\"editrail-tree\">\n".
            $this->getTreeHtml($this->yellow->content->getRootLocation($page->location))."</div>\n";
        $output .= $this->getMediaHtml();
        $output .= "<a class=\"editrail-item editrail-exit\" href=\"".$this->getLocationPlain($page)."\">".
            $this->yellow->language->getTextHtml("editrailExit")."</a>\n";
        $output .= "</div>\n";
        return $output;
    }

    // Return the files of the media directory, to insert, to delete and to add to
    public function getMediaHtml() {
        $id = "editrail-media-toggle";
        $output = "<div class=\"editrail-media\">\n";
        $output .= "<input class=\"editrail-branch\" type=\"checkbox\" id=\"".$id."\" />";
        $output .= "<span class=\"editrail-page editrail-media-head\">";
        $output .= "<label class=\"editrail-twisty\" for=\"".$id."\" aria-hidden=\"true\"></label>";
        $output .= "<span class=\"editrail-title\">".$this->yellow->language->getTextHtml("editrailMedia")."</span>";
        $output .= "</span>\n";
        $output .= "<div class=\"editrail-media-list\">\n<ul>\n";
        foreach ($this->getMediaFiles() as $file) {
            $output .= $this->getMediaFileHtml($file);
        }
        $output .= "</ul>\n";
        $output .= "<label class=\"editrail-upload\">".
            $this->yellow->language->getTextHtml("editrailUpload").
            "<input type=\"file\" multiple=\"multiple\" /></label>\n";
        $output .= "</div>\n</div>\n";
        return $output;
    }

    // Return the files that are worth showing, the images of this website
    public function getMediaFiles() {
        $location = $this->yellow->system->get("coreImageLocation");
        $files = array();
        foreach ($this->yellow->media->index(true) as $file) {
            if (substru($file->getLocation(), 0, strlenu($location))!=$location) continue;
            $files[] = $file;
        }
        usort($files, function ($a, $b) { return strnatcasecmp($a->getLocation(), $b->getLocation()); });
        return $files;
    }

    // Return one file of the media directory
    public function getMediaFileHtml($file) {
        $location = $file->getLocation();
        $name = substru($location, strlenu($this->yellow->system->get("coreImageLocation")));
        $textInsert = $this->yellow->language->getTextHtml("editrailInsertFile");
        $textDelete = $this->yellow->language->getTextHtml("editrailDeleteFile");
        $output = "<li class=\"editrail-file\">\n";
        $output .= "<img src=\"".htmlspecialchars($this->getMediaUrl($location))."\" alt=\"\" loading=\"lazy\" />";
        $output .= "<span class=\"editrail-file-name\" title=\"".htmlspecialchars($name)."\">".
            htmlspecialchars($name)."</span>";
        $output .= "<span class=\"editrail-tools\">";
        $output .= "<button type=\"button\" class=\"editrail-tool editrail-tool-insert\"".
            " data-media=\"".htmlspecialchars($location)."\" data-name=\"".htmlspecialchars($name)."\"".
            " title=\"".$textInsert."\" aria-label=\"".$textInsert."\"></button>";
        $output .= "<button type=\"button\" class=\"editrail-tool editrail-tool-delete\"".
            " data-media=\"".htmlspecialchars($location)."\"".
            " title=\"".$textDelete."\" aria-label=\"".$textDelete."\"></button>";
        $output .= "</span>\n</li>\n";
        return $output;
    }

    // Return page tree HTML, the unlisted pages are shown too
    public function getTreeHtml($location) {
        $pages = $this->yellow->content->getChildren($location, true);
        if (count($pages)==0) return "";
        $output = "<ul>\n";
        foreach ($pages as $pageTree) {
            $treeHtml = $this->getTreeHtml($pageTree->getLocation());
            // a branch is closed, unless the page that is open is somewhere inside it
            $open = $pageTree->isActive() || strposu($treeHtml, "editrail-title active")!==false;
            $id = "editrail-branch-".(++$this->number);
            $output .= "<li>";
            if (!is_string_empty($treeHtml)) {
                $output .= "<input class=\"editrail-branch\" type=\"checkbox\" id=\"".$id."\"".
                    ($open ? " checked" : "")." />";
            }
            $output .= "<span class=\"editrail-page\">";
            if (!is_string_empty($treeHtml)) {
                $output .= "<label class=\"editrail-twisty\" for=\"".$id."\" aria-hidden=\"true\"></label>";
            }
            $class = array("editrail-title");
            if ($pageTree->isActive()) $class[] = "active";
            if (!$pageTree->isVisible()) $class[] = "unlisted";
            $output .= "<a class=\"".implode(" ", $class)."\" href=\"".$pageTree->getLocation(true)."\">".
                $pageTree->getHtml("title")."</a>";
            $output .= $this->getToolsHtml($pageTree);
            $output .= "</span>";
            $output .= $treeHtml;
            $output .= "</li>\n";
        }
        return $output."</ul>\n";
    }

    // Return the buttons of a page in the tree, they show up on hover
    public function getToolsHtml($pageTree) {
        $output = "<span class=\"editrail-tools\">";
        // the button that shows or hides a page says which of the two it is now
        $status = $pageTree->isVisible() ? "status" : "status-hidden";
        $tools = array("edit" => "editrailEditPage", "create" => "editrailCreate",
            $status => $pageTree->isVisible() ? "editrailHidePage" : "editrailShowPage",
            "delete" => "editrailDelete");
        foreach ($tools as $tool=>$text) {
            $text = $this->yellow->language->getTextHtml($text);
            $action = $tool=="status-hidden" ? "status" : $tool;
            $output .= "<a class=\"editrail-tool editrail-tool-".$tool."\" href=\"".
                htmlspecialchars($pageTree->get("editPageUrl"))."#pfadi-".$action."\"".
                " title=\"".$text."\" aria-label=\"".$text."\"></a>";
        }
        return $output."</span>";
    }

    // Return a location without the editing prefix, "/edit/team/" becomes "/team/"
    public function getLocationPage($location) {
        $editLocation = $this->yellow->system->get("editLocation");
        return substru($location, 0, strlenu($editLocation))==$editLocation ?
            substru($location, strlenu($editLocation)-1) : $location;
    }

    // Return the page location without the editing prefix
    public function getLocationPlain($page) {
        return $this->yellow->lookup->normaliseUrl(
            $this->yellow->system->get("coreServerScheme"),
            $this->yellow->system->get("coreServerAddress"),
            $this->yellow->system->get("coreServerBase"), $page->location);
    }

    // Check if the website can be edited right now
    public function isEditable() {
        return $this->yellow->extension->isExisting("edit") && $this->yellow->extension->get("edit")->editable;
    }
}
