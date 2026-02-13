# Orbit

See How All of Your Channels Relate.

## INSTALLATION

1. Copy the entire `orbit` folder to your `system/user/addons` folder.
2. On your EE backend, navigate to `Developer > Addons` (yoursite.com/admin.php?/cp/addons).
3. Scroll to `Third Party Add-Ons`.
4. Find `Orbit` and click `Install`.
5. Navigate to `Add-Ons > Orbit` to view the channel relationship graph.

If you are upgrading from a previous version where the CP backend was not enabled, you will need to uninstall and reinstall the add-on for the control panel page to register.

## OVERVIEW

Orbit provides an interactive force-directed graph that visualizes how your ExpressionEngine channels are connected through relationship fields. Channels appear as colored nodes and relationship fields appear as labeled, directed edges between them.

At a glance you can see:

+ Which channels reference other channels
+ Which relationship fields create those connections
+ How many incoming and outgoing relationships each channel has
+ Whether multiple fields connect the same pair of channels

## GRAPH INTERACTION

### Viewing

When you open Orbit from the Add-Ons menu, the graph automatically renders all channels that participate in at least one relationship. Channels with no relationship fields are excluded to keep the visualization clean.

### Dragging

Click and drag any channel node to reposition it. The force simulation re-engages while dragging, so connected nodes will shift naturally in response. Release the node and it will stay where the simulation settles it.

### Tooltips

Hover over any channel node to see a tooltip with:

+ **Channel name**
+ **Outgoing connections**: How many relationship fields originate from this channel
+ **Incoming connections**: How many relationship fields point to this channel
+ **Field details**: Each outgoing field name and its target channel

### Multi-Edge Display

When multiple relationship fields connect the same two channels, the edges curve apart as Bezier paths so each field label remains readable.

### Responsive Layout

The graph container responds to browser resizing. The simulation recalculates automatically when the viewport changes.

## EMPTY STATE

If no relationship fields exist in your ExpressionEngine installation, the graph area displays a message prompting you to create relationship fields between your channels.

## HOW IT WORKS

Orbit queries your channel and field configuration at render time. It does not store any data of its own and creates no database tables.

### Channel Discovery

All channels are fetched via the EE Model service. Each channel that participates in at least one relationship becomes a node in the graph.

### Relationship Field Detection

Orbit queries all fields with `field_type` of `relationship`. For each field, it determines:

+ **Source channels**: Which channels contain this field, resolved through both direct field assignments and field group assignments.
+ **Target channels**: Which channels the field is configured to relate to, read from the field's settings. If no specific channels are selected, all channels are considered valid targets.

### Graph Data

The resulting nodes and edges are passed to the view as JSON. The graph is rendered entirely client-side using inline vanilla JavaScript with no external dependencies.

## CONTROL PANEL

Orbit adds a single control panel page accessible from `Add-Ons > Orbit`. There are no additional settings or configuration pages.

## SUPPORT

We want to make sure you have what you need on this. Email <support@triplenerdscore.net> for help.
