import { configure } from '@storybook/html';
// Ideally we'd import at the story level, but we will need to reorganise our styles first.
import '../resources/skins.minerva.base.styles/skin.less';
import '../resources/skins.minerva.content.styles/index.less';
import '../resources/skins.minerva.scripts/styles.less';

import '../skinStyles/mediawiki.hlist/minerva.less';
import '../skinStyles/mediawiki.ui.icon/mediawiki.ui.icon.less';

// Automatically import all files ending in `*.stories.js`.
configure(require.context('../stories', true, /\.stories\.js$/), module);
