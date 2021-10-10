// Loads Exhibit API after document is ready.
// @author @lmorillas

var Exhibit_urlPrefix = "//api.simile-widgets.org/exhibit/HEAD/";
var Exhibit_TimeExtension_urlPrefix = Exhibit_urlPrefix + "extensions/time/";
var Exhibit_MapExtension_urlPrefix = Exhibit_urlPrefix + "extensions/map/";
var ex_url = "//api.simile-widgets.org/exhibit/HEAD/exhibit-api.js";

window.Exhibit_parameters="?autoCreate=false";

window.tableStyler = function(table, database) {
    $(table).addClass("cargoTable");
};

jQuery("#loading_exhibit").show();

jQuery.ajax({
    url: ex_url,
    dataType: "script",
    cache: true
});

jQuery(document).on("scriptsLoaded.exhibit", function(evt) {
    jQuery("#loading_exhibit").hide();
});

jQuery(document).on("staticComponentsRegistered.exhibit", function(evt) {
    Exhibit.autoCreate();
});
