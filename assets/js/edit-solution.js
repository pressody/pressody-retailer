import { data, element, html } from './utils/index.js';
import SolutionPreview from './components/solution-preview.js';
import './data/solutions.js';

const { useDispatch, useSelect } = data;
const { Fragment, render } = element;

// noinspection JSUnresolvedVariable,JSHint
const { editedPostId } = _pixelgradeltRetailerEditSolutionData;

function App( props ) {
	const { postId } = props;

	const {
		setPostId,
	} = useDispatch( 'pixelgradelt_retailer/solutions' );

	setPostId( postId );

	const solutions = useSelect( ( select ) => {
		return select( 'pixelgradelt_retailer/solutions' ).getSolutions();
	} );

	return html`
		<${ Fragment }>
			<${ SolutionPreview }
				solutions=${ solutions }
				postId=${ postId }
			/>
		</${ Fragment }>
	`;
}

render(
	html`<${ App } postId=${ editedPostId } />`,
	document.getElementById( 'pixelgradelt_retailer-solution-preview' )
);
