<?php
// Editrail extension, https://github.com/pfadfinder26/yellow-editrail
// Based on Datenstrom Yellow, https://datenstrom.se/yellow/

class YellowEditrail {
    const VERSION = "0.1.8";
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
        $this->yellow->language->setDefault("EditrailDeleteFileAsk", "Delete the file @file?", "en");
        $this->yellow->language->setDefault("EditrailDeleteFileAsk", "Die Datei @file löschen?", "de");
        $this->yellow->language->setDefault("EditrailUploadSize", "@file is bigger than @size, the most this website takes.", "en");
        $this->yellow->language->setDefault("EditrailUploadSize", "@file ist größer als @size, mehr nimmt diese Website nicht.", "de");
        $this->yellow->language->setDefault("EditrailUploadKind", "@file is not a kind of file this website takes.", "en");
        $this->yellow->language->setDefault("EditrailUploadKind", "@file ist keine Datei, die diese Website nimmt.", "de");
        $this->yellow->language->setDefault("EditrailShared", "Building blocks", "en");
        $this->yellow->language->setDefault("EditrailShared", "Bausteine", "de");
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

    // Return the file name of a media file, empty for anything outside the media directory
    public function getMediaFileName($location) {
        $base = $this->yellow->system->get("coreServerBase");
        if (substru($location, 0, strlenu($base))==$base) $location = substru($location, strlenu($base));
        $media = $this->yellow->system->get("coreMediaLocation");
        if (substru($location, 0, strlenu($media))!=$media) return "";
        if (strposu($location, "..")!==false) return "";
        $fileName = $this->yellow->system->get("coreMediaDirectory").substru($location, strlenu($media));
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
        $output .= $this->getSharedHtml($page);
        $output .= "<a class=\"editrail-item editrail-exit\" href=\"".$this->getLocationPlain($page)."\">".
            $this->yellow->language->getTextHtml("editrailExit")."</a>\n";
        $output .= "</div>\n";
        return $output;
    }

    // Return the shared pages, the blocks that stand on more than one page
    public function getSharedHtml($page) {
        $pages = $this->yellow->content->getShared($this->getLocationPage($page->location));
        if (count($pages)==0) return "";
        $id = "editrail-shared-toggle";
        $editLocation = rtrim($this->yellow->system->get("editLocation"), "/");
        $output = "<div class=\"editrail-shared\">\n";
        $output .= "<input class=\"editrail-branch\" type=\"checkbox\" id=\"".$id."\" />";
        $output .= "<label class=\"editrail-page editrail-shared-head\" for=\"".$id."\">";
        $output .= "<span class=\"editrail-twisty\" aria-hidden=\"true\"></span>";
        $output .= "<span class=\"editrail-title\">".$this->yellow->language->getTextHtml("editrailShared")."</span>";
        $output .= "</label>\n<ul>\n";
        foreach ($pages as $pageShared) {
            // a shared page cannot be visited, but it can be edited, the edit extension does that
            $location = $editLocation.$pageShared->location;
            $class = "editrail-title".($this->yellow->page->location==$pageShared->location ? " active" : "");
            $output .= "<li><span class=\"editrail-page\">";
            $output .= "<a class=\"".$class."\" href=\"".htmlspecialchars($location)."#pfadi-edit\">".
                htmlspecialchars(basename($pageShared->location))."</a>";
            $output .= "</span></li>\n";
        }
        $output .= "</ul>\n</div>\n";
        return $output;
    }

    // Return the files of the media directory, to insert, to delete and to add to
    public function getMediaHtml() {
        $id = "editrail-media-toggle";
        $output = "<div class=\"editrail-media\">\n";
        $output .= "<input class=\"editrail-branch\" type=\"checkbox\" id=\"".$id."\" />";
        // the whole line opens and closes the files, not only the twisty
        $output .= "<label class=\"editrail-page editrail-media-head\" for=\"".$id."\">";
        $output .= "<span class=\"editrail-twisty\" aria-hidden=\"true\"></span>";
        $output .= "<span class=\"editrail-title\">".$this->yellow->language->getTextHtml("editrailMedia")."</span>";
        $output .= "</label>\n";
        $output .= "<div class=\"editrail-media-list\">\n";
        foreach ($this->getMediaRows() as $folder=>$rows) {
            if (!is_string_empty($folder)) {
                $idFolder = "editrail-folder-".(++$this->number);
                $output .= "<input class=\"editrail-branch\" type=\"checkbox\" id=\"".$idFolder."\" />";
                $output .= "<label class=\"editrail-page editrail-folder\" for=\"".$idFolder."\">";
                $output .= "<span class=\"editrail-twisty\" aria-hidden=\"true\"></span>";
                $output .= "<span class=\"editrail-title\">".htmlspecialchars($folder)."</span>";
                $output .= "</label>\n";
            }
            $output .= "<ul>\n".implode("", $rows)."</ul>\n";
        }
        $output .= "<label class=\"editrail-upload\">".
            $this->yellow->language->getTextHtml("editrailUpload").
            "<input type=\"file\" multiple=\"multiple\" accept=\"".
            htmlspecialchars($this->yellow->system->get("editUploadExtensions"))."\" /></label>\n";
        $output .= "</div>\n</div>\n";
        return $output;
    }

    // Return the rows of the files, by the folder they are in, the files of this website and
    // the ones an extension offers together, so a file that is not here yet stands where it
    // would land
    public function getMediaRows() {
        $rows = array();
        foreach ($this->getMediaFiles() as $folder=>$files) {
            foreach ($files as $file) {
                $rows[$folder][basename($file->getLocation())] = $this->getMediaFileHtml($file);
            }
        }
        foreach ($this->yellow->extension->data as $value) {
            if (!method_exists($value["object"], "onEditrailMedia")) continue;
            foreach ((array)$value["object"]->onEditrailMedia() as $entry) {
                if (!isset($entry["folder"]) || !isset($entry["name"]) || !isset($entry["html"])) continue;
                if (isset($rows[$entry["folder"]][$entry["name"]])) continue;
                $rows[$entry["folder"]][$entry["name"]] = $entry["html"];
            }
        }
        uksort($rows, function ($a, $b) { return strnatcasecmp($a, $b); });
        foreach ($rows as $folder=>$files) {
            uksort($files, function ($a, $b) { return strnatcasecmp($a, $b); });
            $rows[$folder] = $files;
        }
        return $rows;
    }

    // Return the files of this website, by the folder they are in, the loose ones first
    public function getMediaFiles() {
        $location = $this->yellow->system->get("coreMediaLocation");
        // the thumbnails are made by this website itself, nobody edits them
        $thumbnails = $this->yellow->system->get("coreThumbnailLocation");
        $folders = array("" => array());
        foreach ($this->yellow->media->index(true) as $file) {
            if (substru($file->getLocation(), 0, strlenu($location))!=$location) continue;
            if (substru($file->getLocation(), 0, strlenu($thumbnails))==$thumbnails) continue;
            $name = substru($file->getLocation(), strlenu($location));
            $folder = strposu($name, "/")!==false ? dirname($name) : "";
            $folders[$folder][] = $file;
        }
        if (count($folders[""])==0) unset($folders[""]);
        uksort($folders, function ($a, $b) { return strnatcasecmp($a, $b); });
        foreach ($folders as $folder=>$files) {
            usort($files, function ($a, $b) { return strnatcasecmp($a->getLocation(), $b->getLocation()); });
            $folders[$folder] = $files;
        }
        return $folders;
    }

    // Return one file of the media directory
    public function getMediaFileHtml($file) {
        $location = $file->getLocation();
        $name = substru($location, strlenu($this->yellow->system->get("coreMediaLocation")));
        $textInsert = $this->yellow->language->getTextHtml("editrailInsertFile");
        $textDelete = $this->yellow->language->getTextHtml("editrailDeleteFile");
        $markdown = $this->getMediaFileMarkdown($location, $name);
        $output = "<li class=\"editrail-file\">\n";
        if ($this->isImage($name)) {
            $output .= "<img src=\"".htmlspecialchars($this->getMediaUrl($location))."\" alt=\"\" loading=\"lazy\" />";
        } else {
            $output .= "<span class=\"editrail-file-icon\" aria-hidden=\"true\"></span>";
        }
        $output .= "<span class=\"editrail-file-name\" title=\"".htmlspecialchars($name)."\">".
            htmlspecialchars($this->getMediaFileTitle($name))."</span>";
        $output .= "<span class=\"editrail-tools\">";
        $output .= "<button type=\"button\" class=\"editrail-tool editrail-tool-insert\"".
            " data-media=\"".htmlspecialchars($location)."\" data-markdown=\"".htmlspecialchars($markdown)."\"".
            " title=\"".$textInsert."\" aria-label=\"".$textInsert."\"></button>";
        $output .= "<button type=\"button\" class=\"editrail-tool editrail-tool-delete\"".
            " data-media=\"".htmlspecialchars($location)."\"".
            " title=\"".$textDelete."\" aria-label=\"".$textDelete."\"></button>";
        $output .= $this->getMediaFileExtraHtml($location);
        $output .= "</span>\n</li>\n";
        return $output;
    }

    // Return what is written into a page for a file. An extension that fetched the file from
    // somewhere may write its own, the link it came from instead of the copy that lies here
    public function getMediaFileMarkdown($location, $name) {
        foreach ($this->yellow->extension->data as $value) {
            if (!method_exists($value["object"], "onEditrailFileMarkdown")) continue;
            $markdown = strval($value["object"]->onEditrailFileMarkdown($location));
            if (!is_string_empty($markdown)) return $markdown;
        }
        return $this->isImage($name) ? "![](".$location.")" :
            "[".$this->getMediaFileTitle($name)."](".$location.")";
    }

    // Return the name of a file as it is worth reading, a file that was fetched from somewhere
    // carries the beginning of its hash in the name, which says nothing to anybody
    public function getMediaFileTitle($name) {
        $name = basename($name);
        return preg_replace("/-[0-9a-f]{10}(\.[^.]+)?$/", "$1", $name);
    }

    // Check if a file is a picture, only a picture is worth a thumbnail
    public function isImage($name) {
        return in_array(strtoloweru(pathinfo($name, PATHINFO_EXTENSION)),
            array("png", "jpg", "jpeg", "gif", "webp", "svg", "avif"));
    }

    // Return what other extensions have to say about a file. An extension with a method
    // onEditrailFile gets the location of a file and may hand back buttons of its own, the
    // classes editrail-tool-open and editrail-tool-reload carry an icon for them
    public function getMediaFileExtraHtml($location) {
        $output = "";
        foreach ($this->yellow->extension->data as $value) {
            if (method_exists($value["object"], "onEditrailFile")) {
                $output .= strval($value["object"]->onEditrailFile($location));
            }
        }
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

    // Return the page location without the editing prefix, home for a page that cannot be visited
    public function getLocationPlain($page) {
        $location = $this->yellow->lookup->isSharedLocation($page->location) ?
            $this->yellow->content->getHomeLocation($page->location) : $page->location;
        return $this->yellow->lookup->normaliseUrl(
            $this->yellow->system->get("coreServerScheme"),
            $this->yellow->system->get("coreServerAddress"),
            $this->yellow->system->get("coreServerBase"), $location);
    }

    // Check if the website can be edited right now, by somebody who is logged in
    public function isEditable() {
        if (!$this->yellow->extension->isExisting("edit")) return false;
        $edit = $this->yellow->extension->get("edit");
        return $edit->editable && $edit->response->isUser();
    }
}
