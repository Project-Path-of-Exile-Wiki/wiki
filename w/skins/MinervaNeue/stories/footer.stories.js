import mustache from 'mustache';
import template from '!!raw-loader!../includes/Skins/footer.mustache';
import { lastModifiedBar, lastModifiedBarActive } from './lastModifiedBar.stories';
import { placeholder } from './utils';
import './footer.less';

export default {
	title: 'Footer'
};

const FOOTER_TEMPLATE_DATA = {
	headinghtml: 'Site title OR Logo',
	licensehtml: 'Content is available under <a rel="nofollow" href="#">Reading Web 3.0 License</a> unless otherwise noted.',
	dataAfterContent: placeholder( 'Extensions can add here e.g. Related Articles.' ),
	footer: true,
	lists: [
		{
			name: 'places',
			items: [
				{
					category: 'places',
					name: 'terms-use',
					linkhtml: '<a href="#">Terms of Use</a>'
				},
				{
					category: 'places',
					name: 'privacy',
					linkhtml: '<a href="#">Privacy</a>'
				},
				{
					category: 'places',
					name: 'desktop-toggle',
					linkhtml: '<a href="#">Desktop</a>'
				}
			]
		}
	]
};

export const footer = () =>
	mustache.render( template, Object.assign( FOOTER_TEMPLATE_DATA, {
		lastmodified: lastModifiedBar()
	} ) );

export const footerRecentEdit = () =>
	mustache.render( template, Object.assign( FOOTER_TEMPLATE_DATA, {
		lastmodified: lastModifiedBarActive()
	} ) );
