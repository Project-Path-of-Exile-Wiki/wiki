/**
 * JS code for Special:CargoTableDiagram
 *
 * Code based heavily on the example at https://stackoverflow.com/questions/28959187/draw-an-erm-with-dagre-d3
 *
 * @author Yaron Koren
 */

var width = window.innerWidth,
	svg = d3.select("svg.cargo-table-svg"),
	inner = svg.append("g");

svg.attr('width', width);

inner.attr('id', 'g-main');

// Set up zoom support
    /*
    var zoom = d3.behavior.zoom().on("zoom", function() {
        inner.attr("transform", "translate(" + d3.event.translate + ")" +
        "scale(" + d3.event.scale + ")");
    });
    svg.call(zoom);
    */

// create graph
var g = new dagreD3.graphlib.Graph({
	multigraph: false,
	compound: true
}).setGraph({
	rankdir: "LR",
	edgesep: 25,
	nodesep: 0
});

function addTable( g, tableName, tableFields ) {
	var tableID = tableName + '-parent';
	var maxLength = Math.max(...(tableFields.map(el => (el['name'].length + el['type'].length + 2))));
	var tableWidth = 60 + maxLength * 4;
	g.setNode( tableID, {} );
	// Header
	g.setNode( tableName, { label: tableName, class: 'entity-name', labelStyle: 'font-weight: bold;', width: tableWidth } );
	g.setParent( tableName, tableID );

	for ( fieldNum = 0; fieldNum < tableFields.length; fieldNum++ ) {
		var fieldName = tableFields[fieldNum]['name'];
		var fieldType = tableFields[fieldNum]['type'];
		var fieldLabel = fieldName + ': ' + fieldType;
		var fieldID = tableName + '-' + fieldName;
		g.setNode( fieldID, { label: fieldLabel, width: tableWidth } );
		g.setParent( fieldID, tableID );
	}
}

var tableSchemasJSON = $('div.cargo-table-diagram').attr('data-table-schemas');
var tableSchemas = JSON.parse(tableSchemasJSON);
for ( const tableName in tableSchemas ) {
	var tableFields = [
		{ name: '_pageName', type: 'String' },
		{ name: '_pageID', type: 'Integer' }
	];
	for ( const fieldName in tableSchemas[tableName]['mFieldDescriptions'] ) {
		var fieldIsList = tableSchemas[tableName]['mFieldDescriptions'][fieldName]['mIsList'];
		var fieldType = tableSchemas[tableName]['mFieldDescriptions'][fieldName]['mType'];
		if ( fieldIsList ) {
			fieldType = 'List of ' + fieldType;
		}
		tableFields.push( { name: fieldName, type: fieldType } );
	}
	addTable( g, tableName, tableFields );
}

var parentTablesJSON = $('div.cargo-table-diagram').attr('data-parent-tables');
var parentTables = JSON.parse(parentTablesJSON);
for ( const tableName in parentTables ) {
	var parentTablesForThisTable = parentTables[tableName];
	for ( const remoteTableAlias in parentTablesForThisTable ) {
		var parentTableData = parentTablesForThisTable[remoteTableAlias];
		var localFieldID = tableName + '-' + parentTableData['_localField'];
		var remoteFieldID = parentTableData['Name'] + '-' + parentTableData['_remoteField'];
		g.setEdge( localFieldID, remoteFieldID, { label: '', lineInterpolate: 'monotone' } );
	}
}

// Create the renderer
var render = dagreD3.render();

// Run the renderer. This is what draws the final graph.
render(inner, g);

// adjust height
var initialScale = 1; //0.75;
var graphDimensions = document.getElementById('g-main').getBBox();
svg.attr('height', graphDimensions['height'] + 100);

// Center the graph
    /*
    zoom
            .translate([(svg.attr("width") - g.graph().width * initialScale) / 2, 20])
            .scale(initialScale)
            .event(svg);
    */
