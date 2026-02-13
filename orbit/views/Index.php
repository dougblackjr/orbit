<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title"><?= lang('orbit_module_name') ?></h3>
        </div>
    </div>
    <div class="panel-body">
        <?php if (!$hasRelationships): ?>
            <div class="orbit-empty">
                <p><?= lang('orbit_no_relationships') ?></p>
            </div>
        <?php else: ?>
            <div id="orbit-graph-container">
                <svg id="orbit-svg"></svg>
            </div>
            <div id="orbit-tooltip"></div>
        <?php endif; ?>
    </div>
</div>

<style>
    #orbit-graph-container {
        width: 100%;
        height: 600px;
        position: relative;
        overflow: hidden;
        border: 1px solid var(--ee-border-color, #dfe0ef);
        border-radius: 6px;
        background: var(--ee-bg-blank, #fff);
    }

    #orbit-svg {
        width: 100%;
        height: 100%;
        display: block;
    }

    #orbit-tooltip {
        position: fixed;
        padding: 8px 12px;
        background: var(--ee-bg-0, #282433);
        color: var(--ee-text-primary, #fff);
        border-radius: 4px;
        font-size: 12px;
        line-height: 1.4;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.15s;
        z-index: 10000;
        max-width: 280px;
    }

    #orbit-tooltip.visible {
        opacity: 1;
    }

    .orbit-empty {
        text-align: center;
        padding: 60px 20px;
        color: var(--ee-text-secondary, #8f90b6);
    }

    .orbit-node-label {
        font-size: 11px;
        font-weight: 600;
        fill: var(--ee-text-primary, #0d0d19);
        text-anchor: middle;
        pointer-events: none;
        user-select: none;
    }

    .orbit-edge-label {
        font-size: 9px;
        fill: var(--ee-text-secondary, #8f90b6);
        text-anchor: middle;
        pointer-events: none;
        user-select: none;
    }

    .orbit-edge {
        fill: none;
        stroke: var(--ee-border-color, #dfe0ef);
        stroke-width: 1.5;
    }

    .orbit-node {
        cursor: grab;
        stroke: #fff;
        stroke-width: 2;
    }

    .orbit-node:active {
        cursor: grabbing;
    }
</style>

<?php if ($hasRelationships): ?>
<script>
(function() {
    var graphData = <?= $graphData ?>;
    var nodes = graphData.nodes;
    var edges = graphData.edges;

    var colors = [
        '#5D63D1', '#F5222D', '#FA8C16', '#52C41A', '#13C2C2',
        '#1890FF', '#722ED1', '#EB2F96', '#A0D911', '#FAAD14',
        '#2F54EB', '#08979C', '#389E0D', '#D48806', '#CF1322'
    ];

    // Assign colors to nodes
    for (var i = 0; i < nodes.length; i++) {
        nodes[i].color = colors[i % colors.length];
    }

    // Build node index
    var nodeIndex = {};
    for (var i = 0; i < nodes.length; i++) {
        nodeIndex[nodes[i].id] = i;
    }

    // Count connections per node
    for (var i = 0; i < nodes.length; i++) {
        nodes[i].connectionCount = 0;
    }
    for (var i = 0; i < edges.length; i++) {
        var si = nodeIndex[edges[i].source];
        var ti = nodeIndex[edges[i].target];
        if (si !== undefined) nodes[si].connectionCount++;
        if (ti !== undefined) nodes[ti].connectionCount++;
    }

    // Count edges between same pair for multi-edge offset
    var edgePairCount = {};
    var edgePairIndex = {};
    for (var i = 0; i < edges.length; i++) {
        var key = Math.min(edges[i].source, edges[i].target) + '-' + Math.max(edges[i].source, edges[i].target);
        if (!edgePairCount[key]) edgePairCount[key] = 0;
        edgePairCount[key]++;
        edgePairIndex[i] = edgePairCount[key] - 1;
    }

    var container = document.getElementById('orbit-graph-container');
    var svg = document.getElementById('orbit-svg');
    var tooltip = document.getElementById('orbit-tooltip');
    var width = container.clientWidth;
    var height = container.clientHeight;

    // SVG namespace
    var ns = 'http://www.w3.org/2000/svg';

    // Create arrowhead marker
    var defs = document.createElementNS(ns, 'defs');
    var marker = document.createElementNS(ns, 'marker');
    marker.setAttribute('id', 'orbit-arrowhead');
    marker.setAttribute('viewBox', '0 0 10 6');
    marker.setAttribute('refX', '10');
    marker.setAttribute('refY', '3');
    marker.setAttribute('markerWidth', '10');
    marker.setAttribute('markerHeight', '6');
    marker.setAttribute('orient', 'auto-start-reverse');
    var arrowPath = document.createElementNS(ns, 'path');
    arrowPath.setAttribute('d', 'M0,0 L10,3 L0,6 Z');
    arrowPath.setAttribute('fill', '#b0b0cc');
    marker.appendChild(arrowPath);
    defs.appendChild(marker);
    svg.appendChild(defs);

    // Create a group for edges (drawn first, under nodes)
    var edgeGroup = document.createElementNS(ns, 'g');
    svg.appendChild(edgeGroup);

    // Create a group for edge labels
    var edgeLabelGroup = document.createElementNS(ns, 'g');
    svg.appendChild(edgeLabelGroup);

    // Create a group for nodes
    var nodeGroup = document.createElementNS(ns, 'g');
    svg.appendChild(nodeGroup);

    var nodeRadius = 20;

    // Initialize node positions in a circle
    var cx = width / 2;
    var cy = height / 2;
    var layoutRadius = Math.min(width, height) * 0.3;

    for (var i = 0; i < nodes.length; i++) {
        var angle = (2 * Math.PI * i) / nodes.length;
        nodes[i].x = cx + layoutRadius * Math.cos(angle);
        nodes[i].y = cy + layoutRadius * Math.sin(angle);
        nodes[i].vx = 0;
        nodes[i].vy = 0;
        nodes[i].fx = null;
        nodes[i].fy = null;
    }

    // Create SVG elements for edges
    var edgeElements = [];
    var edgeLabelElements = [];
    for (var i = 0; i < edges.length; i++) {
        var path = document.createElementNS(ns, 'path');
        path.setAttribute('class', 'orbit-edge');
        path.setAttribute('marker-end', 'url(#orbit-arrowhead)');
        edgeGroup.appendChild(path);
        edgeElements.push(path);

        var label = document.createElementNS(ns, 'text');
        label.setAttribute('class', 'orbit-edge-label');
        label.textContent = edges[i].field;
        edgeLabelGroup.appendChild(label);
        edgeLabelElements.push(label);
    }

    // Create SVG elements for nodes
    var nodeCircles = [];
    var nodeLabels = [];
    for (var i = 0; i < nodes.length; i++) {
        var circle = document.createElementNS(ns, 'circle');
        circle.setAttribute('class', 'orbit-node');
        circle.setAttribute('r', nodeRadius);
        circle.setAttribute('fill', nodes[i].color);
        circle.dataset.index = i;
        nodeGroup.appendChild(circle);
        nodeCircles.push(circle);

        var label = document.createElementNS(ns, 'text');
        label.setAttribute('class', 'orbit-node-label');
        label.setAttribute('dy', nodeRadius + 14);
        label.textContent = nodes[i].name;
        nodeGroup.appendChild(label);
        nodeLabels.push(label);
    }

    // Force simulation parameters
    var repulsionStrength = 5000;
    var attractionStrength = 0.005;
    var centerGravity = 0.01;
    var damping = 0.85;
    var edgeRestLength = 150;

    function simulate() {
        // Reset forces
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].fx !== null) continue;
            nodes[i].ax = 0;
            nodes[i].ay = 0;
        }

        // Repulsion between all pairs (Coulomb)
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].fx !== null) continue;
            for (var j = i + 1; j < nodes.length; j++) {
                var dx = nodes[i].x - nodes[j].x;
                var dy = nodes[i].y - nodes[j].y;
                var dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 1) dist = 1;
                var force = repulsionStrength / (dist * dist);
                var fx = force * dx / dist;
                var fy = force * dy / dist;
                nodes[i].ax += fx;
                nodes[i].ay += fy;
                if (nodes[j].fx === null) {
                    nodes[j].ax -= fx;
                    nodes[j].ay -= fy;
                }
            }
        }

        // Attraction along edges (Hooke/spring)
        for (var i = 0; i < edges.length; i++) {
            var si = nodeIndex[edges[i].source];
            var ti = nodeIndex[edges[i].target];
            if (si === undefined || ti === undefined) continue;
            var dx = nodes[ti].x - nodes[si].x;
            var dy = nodes[ti].y - nodes[si].y;
            var dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < 1) dist = 1;
            var displacement = dist - edgeRestLength;
            var force = attractionStrength * displacement;
            var fx = force * dx / dist;
            var fy = force * dy / dist;
            if (nodes[si].fx === null) {
                nodes[si].ax += fx;
                nodes[si].ay += fy;
            }
            if (nodes[ti].fx === null) {
                nodes[ti].ax -= fx;
                nodes[ti].ay -= fy;
            }
        }

        // Center gravity
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].fx !== null) continue;
            nodes[i].ax += (cx - nodes[i].x) * centerGravity;
            nodes[i].ay += (cy - nodes[i].y) * centerGravity;
        }

        // Apply forces with damping
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].fx !== null) {
                nodes[i].x = nodes[i].fx;
                nodes[i].y = nodes[i].fy;
                nodes[i].vx = 0;
                nodes[i].vy = 0;
                continue;
            }
            nodes[i].vx = (nodes[i].vx + nodes[i].ax) * damping;
            nodes[i].vy = (nodes[i].vy + nodes[i].ay) * damping;
            nodes[i].x += nodes[i].vx;
            nodes[i].y += nodes[i].vy;

            // Keep within bounds
            nodes[i].x = Math.max(nodeRadius + 5, Math.min(width - nodeRadius - 5, nodes[i].x));
            nodes[i].y = Math.max(nodeRadius + 5, Math.min(height - nodeRadius - 5, nodes[i].y));
        }
    }

    function render() {
        // Update edges
        for (var i = 0; i < edges.length; i++) {
            var si = nodeIndex[edges[i].source];
            var ti = nodeIndex[edges[i].target];
            if (si === undefined || ti === undefined) continue;

            var sx = nodes[si].x;
            var sy = nodes[si].y;
            var tx = nodes[ti].x;
            var ty = nodes[ti].y;

            var key = Math.min(edges[i].source, edges[i].target) + '-' + Math.max(edges[i].source, edges[i].target);
            var total = edgePairCount[key];
            var idx = edgePairIndex[i];

            // Shorten line to stop at node edge (accounting for arrowhead)
            var dx = tx - sx;
            var dy = ty - sy;
            var dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < 1) dist = 1;
            var ux = dx / dist;
            var uy = dy / dist;

            var startX = sx + ux * nodeRadius;
            var startY = sy + uy * nodeRadius;
            var endX = tx - ux * (nodeRadius + 8);
            var endY = ty - uy * (nodeRadius + 8);

            if (total > 1) {
                // Curved path for multi-edges
                var offset = (idx - (total - 1) / 2) * 40;
                var mx = (startX + endX) / 2 + (-uy) * offset;
                var my = (startY + endY) / 2 + ux * offset;
                edgeElements[i].setAttribute('d',
                    'M' + startX + ',' + startY +
                    ' Q' + mx + ',' + my +
                    ' ' + endX + ',' + endY
                );
                edgeLabelElements[i].setAttribute('x', mx);
                edgeLabelElements[i].setAttribute('y', my - 4);
            } else {
                // Straight line
                edgeElements[i].setAttribute('d',
                    'M' + startX + ',' + startY +
                    ' L' + endX + ',' + endY
                );
                edgeLabelElements[i].setAttribute('x', (startX + endX) / 2);
                edgeLabelElements[i].setAttribute('y', (startY + endY) / 2 - 6);
            }
        }

        // Update nodes
        for (var i = 0; i < nodes.length; i++) {
            nodeCircles[i].setAttribute('cx', nodes[i].x);
            nodeCircles[i].setAttribute('cy', nodes[i].y);
            nodeLabels[i].setAttribute('x', nodes[i].x);
            nodeLabels[i].setAttribute('y', nodes[i].y);
        }
    }

    // Run initial simulation ticks
    for (var t = 0; t < 300; t++) {
        simulate();
    }
    render();

    // Continue animating for smoothness after initial layout
    var animating = true;
    var ticksRemaining = 200;

    function tick() {
        if (!animating) return;
        simulate();
        render();
        ticksRemaining--;
        if (ticksRemaining > 0 || dragging) {
            requestAnimationFrame(tick);
        } else {
            animating = false;
        }
    }
    requestAnimationFrame(tick);

    // Drag interaction
    var dragging = null;
    var dragOffsetX = 0;
    var dragOffsetY = 0;

    function onMouseDown(e) {
        var target = e.target;
        if (target.classList.contains('orbit-node')) {
            var idx = parseInt(target.dataset.index, 10);
            dragging = idx;
            var rect = svg.getBoundingClientRect();
            dragOffsetX = e.clientX - rect.left - nodes[idx].x;
            dragOffsetY = e.clientY - rect.top - nodes[idx].y;
            nodes[idx].fx = nodes[idx].x;
            nodes[idx].fy = nodes[idx].y;

            if (!animating) {
                animating = true;
                ticksRemaining = 100;
                requestAnimationFrame(tick);
            }

            e.preventDefault();
        }
    }

    function onMouseMove(e) {
        if (dragging !== null) {
            var rect = svg.getBoundingClientRect();
            var mx = e.clientX - rect.left - dragOffsetX;
            var my = e.clientY - rect.top - dragOffsetY;
            nodes[dragging].fx = Math.max(nodeRadius, Math.min(width - nodeRadius, mx));
            nodes[dragging].fy = Math.max(nodeRadius, Math.min(height - nodeRadius, my));
            nodes[dragging].x = nodes[dragging].fx;
            nodes[dragging].y = nodes[dragging].fy;
        }
    }

    function onMouseUp(e) {
        if (dragging !== null) {
            nodes[dragging].fx = null;
            nodes[dragging].fy = null;
            ticksRemaining = Math.max(ticksRemaining, 100);
            dragging = null;
        }
    }

    svg.addEventListener('mousedown', onMouseDown);
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);

    // Tooltip interaction
    for (var i = 0; i < nodeCircles.length; i++) {
        (function(idx) {
            nodeCircles[idx].addEventListener('mouseenter', function(e) {
                var node = nodes[idx];
                var outgoing = 0;
                var incoming = 0;
                var fields = [];
                for (var j = 0; j < edges.length; j++) {
                    if (edges[j].source === node.id) {
                        outgoing++;
                        fields.push(edges[j].field + ' \u2192 ' + nodes[nodeIndex[edges[j].target]].name);
                    }
                    if (edges[j].target === node.id) {
                        incoming++;
                    }
                }
                var html = '<strong>' + node.name + '</strong><br>' +
                    'Outgoing: ' + outgoing + ' | Incoming: ' + incoming;
                if (fields.length > 0) {
                    html += '<br><br>' + fields.join('<br>');
                }
                tooltip.innerHTML = html;
                tooltip.classList.add('visible');
            });

            nodeCircles[idx].addEventListener('mousemove', function(e) {
                tooltip.style.left = (e.clientX + 12) + 'px';
                tooltip.style.top = (e.clientY - 10) + 'px';
            });

            nodeCircles[idx].addEventListener('mouseleave', function() {
                tooltip.classList.remove('visible');
            });
        })(i);
    }

    // Responsive resize
    var resizeObserver = new ResizeObserver(function(entries) {
        width = container.clientWidth;
        height = container.clientHeight;
        cx = width / 2;
        cy = height / 2;

        if (!animating) {
            animating = true;
            ticksRemaining = 50;
            requestAnimationFrame(tick);
        }
    });
    resizeObserver.observe(container);
})();
</script>
<?php endif; ?>
