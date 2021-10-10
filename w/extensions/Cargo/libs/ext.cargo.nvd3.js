/**
 * Code for dealing with the NVD3 JavaScript library.
 *
 * @ingroup Cargo
 * @author Yaron Koren
 */
$(document).ready(function() {

	$('.cargoBarChart').each( function() {

		var dataURL = decodeURI( $(this).attr('dataurl') );
		var innerSVG = $(this).find('svg');

		d3.json( dataURL, function(data) {
			// Quick exit.
			if ( data == null ) return;

			var maxLabelSize = 0;
			var numbersIncludeDecimalPoints = false;
			for ( var i in data ) {
				for ( var j in data[i]['values'] ) {
					var curLabel = data[i]['values'][j]['label'];
					maxLabelSize = Math.max( maxLabelSize, curLabel.length );
					if ( !numbersIncludeDecimalPoints ) {
						var curValue = data[i]['values'][j]['value'];
						if ( curValue.toString().indexOf( '.' ) >= 0 ) {
							numbersIncludeDecimalPoints = true;
						}
					}
				}
			}
			var labelsWidth = Math.round( ( maxLabelSize + 1) * 7 );

			nv.addGraph(function() {
				if ( innerSVG.height() == 1 ) {
					var numLabels = data.length * data[0]['values'].length;
					var graphHeight = ( numLabels + 2 ) * 22;
					innerSVG.height( graphHeight );
				}

				var chart = nv.models.multiBarHorizontalChart()
					.x(function(d) { return d.label })
					.y(function(d) { return d.value })
					.margin({top: 0, right: 0, bottom: 0, left: labelsWidth })
					.showValues(true)           //Show bar value next to each bar.
					.tooltips(false)             //Show tooltips on hover.
					.duration(350)
					.showControls(false);        //Allow user to switch between "Grouped" and "Stacked" mode.

				if ( !numbersIncludeDecimalPoints ) {
					// These are all integers - don't
					// show decimal points in the chart.
					chart.yAxis.tickFormat(d3.format(',f'));
					chart.valueFormat(d3.format('d'));
				}

				d3.selectAll(innerSVG)
					.datum(data)
					.call(chart);

				nv.utils.windowResize(chart.update);

				return chart;
			});
		});

	});

	$('.cargoPieChart').each( function() {

		var dataURL = decodeURI( $(this).attr('dataurl') );
		var innerSVG = $(this).find('svg');
		var hideLegendStr = $(this).attr('data-hide-legend');
		var showLegend = ( hideLegendStr != '1' );
		var labelsType = showLegend ? 'value' : 'key';
		var colorsStr = $(this).attr('data-colors');
		if ( colorsStr ) {
			var colorsVal = JSON.parse( colorsStr );
		}

		d3.json( dataURL, function(data) {
			// Pie chart format uses only a
			// single array of key-value pairs
			data = data[0].values;
			// Quick exit.
			if ( data == null ) return;

			var maxLabelSize = 0;
			var numbersIncludeDecimalPoints = false;
			for ( var i in data ) {
				var curLabel = data[i]['label'];
				maxLabelSize = Math.max( maxLabelSize, curLabel.length );
				if ( !numbersIncludeDecimalPoints ) {
					var curValue = data[i]['value'];
					if ( curValue.toString().indexOf( '.' ) >= 0 ) {
						numbersIncludeDecimalPoints = true;
					}
				}
			}

			nv.addGraph(function() {
				var chart = nv.models.pieChart()
					.x(function(d) { return d.label })
					.y(function(d) { return d.value })
					.showLabels(true)           //Show chart value next to each section.
					.labelType(labelsType)
					.showLegend(showLegend)
				if ( colorsVal ) {
					chart.color(colorsVal);
				}

				d3.selectAll(innerSVG)
					.datum(data)
					.transition().duration(350)
					.call(chart);

				return chart;
			});
		});

	});
});
