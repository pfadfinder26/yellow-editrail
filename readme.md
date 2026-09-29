# Editrail 0.1.3

A rail at the side of the window for editors. Developed by Liam Perlaki.

The [edit extension](https://github.com/annaesvensson/yellow-edit) puts a bar at the top of the
page that scrolls away with it. This one puts a rail at the right edge that stays: the buttons of
the edit extension, a page tree, the files of the website, and a button that leaves editing.

## How to install an extension

[Download ZIP file](https://github.com/pfadfinder26/yellow-editrail/archive/refs/heads/main.zip) and copy it into your `system/extensions` folder. [Learn more about extensions](https://github.com/annaesvensson/yellow-update).

## What the rail does

**Slim or wide:** the rail is a column of icons, the top button opens it. Open, every button says
what it does and the page tree and the files appear. It stays the way it was left, in the browser
of that editor.

**The buttons of the edit extension** move into the rail, so editing, creating, deleting and the
account are where everything else is. "+" and "−" get their names back, and nothing jumps around at
the top of the page any more.

**The page tree** shows every page of the website, the unlisted ones as well, in italics. A branch
is closed unless the page that is open is inside it. Under the mouse a page offers four buttons:
edit it, add a page below it, show or hide it, delete it. The button for showing and hiding carries
an open or a crossed out eye, and it writes `Status: unlisted` into the page, the way an editor
would.

**The files** of `media/images` are listed with a thumbnail and a name, each folder a branch of
its own that opens like the branches of the tree. A file can be deleted, it
goes to the trash of Yellow, not into nothing. New files are added with "Add files", which hands
them to the edit extension, so the same rules about size and type apply as everywhere. While a page
is being edited every file also offers to be inserted: the markdown of the file goes where the
cursor is.

**The building blocks** are the pages of `content/shared`, the blocks that stand on more than one
page, footer and letterhead and the templates of new pages. Nothing links to them, so they are
listed here, and each one opens in the window that edits a page, like any other page.

**Leaving editing** goes back to the page without the `/edit` in front of it, it does not log
anybody out.

**The window that edits a page** is centred in the space next to the rail and stays open when
something beside it is clicked, so a stray click does not throw away what was typed.

## What it needs

The edit extension, and somebody logged in. Everything here is shown to an editor only. Deleting a
file asks the server, which checks the login of the edit extension, its permission to upload, and
the token that says the request came from this website. Nothing outside `media/images` can be
deleted, whatever a request claims.

The colours come from four settings of the stylesheet, `--editrail-background`, `--editrail-text`,
`--editrail` and `--editrail-wide`. A theme can set them to its own.
