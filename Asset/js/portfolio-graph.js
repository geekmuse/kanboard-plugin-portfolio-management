/**
 * Portfolio Dependency Graph — D3.js v7 force-directed renderer
 *
 * Reads serialized graph data from the container's data-graph attribute and
 * renders a force-directed dependency graph using the bundled D3.js library.
 * Nodes on the critical path are highlighted in a distinct color.
 *
 * Expected data shape (from DependencyController::graphData()):
 * {
 *   nodes: [{ id, title, project_name, is_active }],
 *   edges: [{ source, target, label, is_resolved }],
 *   critical_path: [<task_id>, ...]
 * }
 */
(function () {
    'use strict';

    window.PortfolioGraph = {
        /**
         * Render the dependency graph inside the given container element.
         *
         * @param {HTMLElement} container
         * @param {Object}      graphData  Parsed graph payload from the controller.
         */
        render: function (container, graphData) {
            if (typeof d3 === 'undefined') {
                container.innerHTML = '<p class="portfolio-empty-state">D3.js not loaded — cannot render graph.</p>';
                return;
            }

            var nodes = (graphData.nodes || []).map(function (n) {
                return Object.assign({}, n);
            });
            var edges = (graphData.edges || []).map(function (e) {
                return Object.assign({}, e);
            });
            var criticalSet = {};
            (graphData.critical_path || []).forEach(function (id) {
                criticalSet[id] = true;
            });

            var width = container.clientWidth || 800;
            var height = Math.max(400, Math.min(600, nodes.length * 40));

            var svg = d3.select(container)
                .append('svg')
                .attr('width', '100%')
                .attr('height', height)
                .attr('class', 'portfolio-dependency-graph-svg');

            svg.append('defs').append('marker')
                .attr('id', 'portfolio-graph-arrow')
                .attr('viewBox', '0 -5 10 10')
                .attr('refX', 20)
                .attr('refY', 0)
                .attr('markerWidth', 6)
                .attr('markerHeight', 6)
                .attr('orient', 'auto')
                .append('path')
                .attr('d', 'M0,-5L10,0L0,5')
                .attr('class', 'portfolio-dependency-arrow');

            var simulation = d3.forceSimulation(nodes)
                .force('link', d3.forceLink(edges).id(function (d) { return d.id; }).distance(120))
                .force('charge', d3.forceManyBody().strength(-300))
                .force('center', d3.forceCenter(width / 2, height / 2))
                .force('collision', d3.forceCollide(30));

            var link = svg.append('g')
                .attr('class', 'portfolio-graph-links')
                .selectAll('line')
                .data(edges)
                .enter()
                .append('line')
                .attr('class', function (d) {
                    return d.is_resolved
                        ? 'portfolio-dependency-edge portfolio-dependency-edge--resolved'
                        : 'portfolio-dependency-edge portfolio-dependency-edge--blocks';
                })
                .attr('marker-end', 'url(#portfolio-graph-arrow)');

            var node = svg.append('g')
                .attr('class', 'portfolio-graph-nodes')
                .selectAll('g')
                .data(nodes)
                .enter()
                .append('g')
                .attr('class', 'portfolio-graph-node')
                .call(
                    d3.drag()
                        .on('start', function (event, d) {
                            if (! event.active) { simulation.alphaTarget(0.3).restart(); }
                            d.fx = d.x;
                            d.fy = d.y;
                        })
                        .on('drag', function (event, d) {
                            d.fx = event.x;
                            d.fy = event.y;
                        })
                        .on('end', function (event, d) {
                            if (! event.active) { simulation.alphaTarget(0); }
                            d.fx = null;
                            d.fy = null;
                        })
                );

            node.append('circle')
                .attr('r', 18)
                .attr('class', function (d) {
                    if (criticalSet[d.id]) {
                        return 'portfolio-dependency-node portfolio-dependency-node--critical';
                    }
                    return d.is_active === 0
                        ? 'portfolio-dependency-node portfolio-dependency-node--closed'
                        : 'portfolio-dependency-node portfolio-dependency-node--active';
                });

            node.append('title')
                .text(function (d) { return '#' + d.id + ' ' + d.title + ' (' + d.project_name + ')'; });

            node.append('text')
                .attr('dy', 4)
                .attr('text-anchor', 'middle')
                .attr('class', 'portfolio-graph-node-label')
                .text(function (d) { return '#' + d.id; });

            simulation.on('tick', function () {
                link
                    .attr('x1', function (d) { return d.source.x; })
                    .attr('y1', function (d) { return d.source.y; })
                    .attr('x2', function (d) { return d.target.x; })
                    .attr('y2', function (d) { return d.target.y; });

                node.attr('transform', function (d) {
                    return 'translate(' + d.x + ',' + d.y + ')';
                });
            });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('portfolio-dependency-graph');
        if (! container) {
            return;
        }

        var rawData = container.getAttribute('data-graph');
        if (! rawData) {
            return;
        }

        try {
            var graphData = JSON.parse(rawData);
            window.PortfolioGraph.render(container, graphData);
        } catch (e) {
            container.innerHTML = '<p class="portfolio-empty-state">Graph data could not be parsed.</p>';
        }
    });
}());
