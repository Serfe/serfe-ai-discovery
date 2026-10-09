<?php

namespace Serfe\AI_Discovery;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode property names are fixed by PHP's DOM API.

use DOMDocument;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Lightweight, dependency-free HTML to Markdown converter. */
final class Html_To_Markdown {
	/** @param string $html HTML fragment. @return string */
	public function convert( $html ) {
		if ( trim( $html ) === '' ) {
			return '';
		}

		$html = preg_replace( '#<(script|style|noscript)\b[^>]*>.*?</\1>#is', '', $html );
		$dom  = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();

		$root     = $dom->getElementsByTagName( 'div' )->item( 0 );
		$markdown = $root ? $this->walk( $root->childNodes ) : '';
		$markdown = preg_replace( "/[ \t]+\n/", "\n", $markdown );
		$markdown = preg_replace( "/\n{3,}/", "\n\n", $markdown );

		return trim( $markdown );
	}

	/** @param \DOMNodeList $nodes Nodes. @return string */
	private function walk( $nodes ) {
		$output = '';
		foreach ( $nodes as $node ) {
			$output .= $this->node( $node );
		}
		return $output;
	}

	/** @param \DOMNode $node Node. @return string */
	private function node( $node ) {
		if ( XML_TEXT_NODE === $node->nodeType ) {
			return preg_replace( '/\s+/', ' ', $node->nodeValue );
		}
		if ( ! ( $node instanceof \DOMElement ) ) {
			return '';
		}

		$tag = strtolower( $node->nodeName );
		if ( $node->hasAttribute( 'hidden' )
			|| strtolower( $node->getAttribute( 'aria-hidden' ) ) === 'true'
			|| preg_match( '/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*hidden)\s*(?:;|$)/i', $node->getAttribute( 'style' ) )
			|| in_array( $tag, array( 'form', 'button', 'select', 'textarea', 'template' ), true ) ) {
			return '';
		}
		$inner = $this->walk( $node->childNodes );

		switch ( $tag ) {
			case 'h1':
				return "\n\n# " . trim( $inner ) . "\n\n";
			case 'h2':
				return "\n\n## " . trim( $inner ) . "\n\n";
			case 'h3':
				return "\n\n### " . trim( $inner ) . "\n\n";
			case 'h4':
				return "\n\n#### " . trim( $inner ) . "\n\n";
			case 'h5':
				return "\n\n##### " . trim( $inner ) . "\n\n";
			case 'h6':
				return "\n\n###### " . trim( $inner ) . "\n\n";
			case 'p':
				return "\n\n" . trim( $inner ) . "\n\n";
			case 'br':
				return "  \n";
			case 'strong':
			case 'b':
				$text = trim( $inner );
				return '' === $text ? '' : '**' . $text . '**';
			case 'em':
			case 'i':
				$text = trim( $inner );
				return '' === $text ? '' : '*' . $text . '*';
			case 'del':
			case 's':
			case 'strike':
				$text = trim( $inner );
				return '' === $text ? '' : '~~' . $text . '~~';
			case 'a':
				$href  = $node->getAttribute( 'href' );
				$text  = trim( $inner );
				$title = $this->markdown_title( $node->getAttribute( 'title' ) );
				return '' === $href ? $text : '[' . ( '' !== $text ? $text : $href ) . '](' . $href . $title . ')';
			case 'img':
				$src   = $node->getAttribute( 'src' );
				$title = $this->markdown_title( $node->getAttribute( 'title' ) );
				return '' === $src ? '' : '![' . $node->getAttribute( 'alt' ) . '](' . $src . $title . ')';
			case 'ul':
				return "\n" . $this->list_items( $node, false ) . "\n";
			case 'ol':
				return "\n" . $this->list_items( $node, true ) . "\n";
			case 'table':
				return $this->table( $node );
			case 'dl':
				return $this->definition_list( $node );
			case 'figcaption':
				return "\n\n" . trim( $inner ) . "\n\n";
			case 'blockquote':
				$lines = array_map(
					function ( $line ) {
						return '> ' . $line; },
					explode( "\n", trim( $inner ) )
				);
				return "\n\n" . implode( "\n", $lines ) . "\n\n";
			case 'pre':
				return $this->fenced_code( $node );
			case 'code':
				return $this->inline_code( $node->textContent );
			case 'kbd':
			case 'samp':
				return $this->inline_code( $node->textContent );
			case 'hr':
				return "\n\n---\n\n";
			case 'input':
				return '';
			case 'li':
				return trim( $inner );
			default:
				return $inner;
		}
	}

	/** @param \DOMElement $node List node. @param bool $ordered Ordered list. @return string */
	private function list_items( $node, $ordered ) {
		$output = '';
		$index  = $ordered && ctype_digit( $node->getAttribute( 'start' ) ) ? (int) $node->getAttribute( 'start' ) : 1;
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType || strtolower( $child->nodeName ) !== 'li' ) {
				continue;
			}

			$content = '';
			$nested  = '';
			foreach ( $child->childNodes as $item_child ) {
				if ( XML_ELEMENT_NODE === $item_child->nodeType && in_array( strtolower( $item_child->nodeName ), array( 'ul', 'ol' ), true ) ) {
					$nested .= $this->list_items( $item_child, strtolower( $item_child->nodeName ) === 'ol' );
					continue;
				}
				$content .= $this->node( $item_child );
			}

			$task    = $this->task_checkbox( $child );
			$output .= ( $ordered ? $index . '. ' : '- ' ) . $task . trim( $content ) . "\n";
			if ( '' !== $nested ) {
				$output .= $this->indent_lines( rtrim( $nested ), 4 ) . "\n";
			}
			++$index;
		}
		return $output;
	}

	/** Return a task-list marker when a list item contains a checkbox. */
	private function task_checkbox( $node ) {
		foreach ( $node->getElementsByTagName( 'input' ) as $input ) {
			if ( strtolower( $input->getAttribute( 'type' ) ) !== 'checkbox' ) {
				continue;
			}
			return $input->hasAttribute( 'checked' ) ? '[x] ' : '[ ] ';
		}

		return '';
	}

	/** Indent non-empty lines so nested lists remain valid Markdown. */
	private function indent_lines( $value, $spaces ) {
		$prefix = str_repeat( ' ', $spaces );
		$lines  = explode( "\n", $value );
		foreach ( $lines as &$line ) {
			if ( '' !== $line ) {
				$line = $prefix . $line;
			}
		}
		unset( $line );

		return implode( "\n", $lines );
	}

	/** Convert an HTML table to a GitHub-flavored Markdown table. */
	private function table( $node ) {
		$rows = array();
		foreach ( $node->getElementsByTagName( 'tr' ) as $row ) {
			$cells = $this->table_cells( $row );
			if ( ! empty( $cells ) ) {
				$rows[] = $cells;
			}
		}

		if ( empty( $rows ) ) {
			return '';
		}

		$columns = count( $rows[0] );
		if ( 0 === $columns ) {
			return '';
		}

		$alignments = $this->table_alignments( $node, $columns );
		$output     = array(
			$this->table_row( $rows[0], $columns ),
			'| ' . implode( ' | ', $alignments ) . ' |',
		);
		foreach ( array_slice( $rows, 1 ) as $row ) {
			$output[] = $this->table_row( $row, $columns );
		}

		return "\n\n" . implode( "\n", $output ) . "\n\n";
	}

	/** Return one normalized Markdown table row. */
	private function table_row( $cells, $columns ) {
		$cells = array_slice( array_pad( $cells, $columns, '' ), 0, $columns );

		return '| ' . implode( ' | ', $cells ) . ' |';
	}

	/** Extract direct table header and data cells from a row. */
	private function table_cells( $row ) {
		$cells = array();
		foreach ( $row->childNodes as $cell ) {
			if ( XML_ELEMENT_NODE !== $cell->nodeType || ! in_array( strtolower( $cell->nodeName ), array( 'th', 'td' ), true ) ) {
				continue;
			}

			$text    = trim( $this->walk( $cell->childNodes ) );
			$text    = preg_replace( '/\s+/', ' ', $text );
			$cells[] = str_replace( '|', '\\|', (string) $text );
		}

		return $cells;
	}

	/** Determine GFM alignment markers from the table's first row. */
	private function table_alignments( $table, $columns ) {
		$alignment = array_fill( 0, $columns, '---' );
		$first_row = null;
		foreach ( $table->getElementsByTagName( 'tr' ) as $row ) {
			$first_row = $row;
			break;
		}
		if ( ! $first_row ) {
			return $alignment;
		}

		$index = 0;
		foreach ( $first_row->childNodes as $cell ) {
			if ( XML_ELEMENT_NODE !== $cell->nodeType || ! in_array( strtolower( $cell->nodeName ), array( 'th', 'td' ), true ) ) {
				continue;
			}
			if ( $index >= $columns ) {
				break;
			}
			$align = strtolower( $cell->getAttribute( 'align' ) );
			$class = $cell->getAttribute( 'class' );
			$style = $cell->getAttribute( 'style' );
			if ( preg_match( '/has-text-align-(left|center|right)/', $class, $matches ) || preg_match( '/text-align\s*:\s*(left|center|right)/i', $style, $matches ) ) {
				$align = strtolower( $matches[1] );
			}
			if ( 'left' === $align ) {
				$alignment[ $index ] = ':---';
			} elseif ( 'center' === $align ) {
				$alignment[ $index ] = ':---:';
			} elseif ( 'right' === $align ) {
				$alignment[ $index ] = '---:';
			}
			++$index;
		}

		return $alignment;
	}

	/** Convert an HTML definition list to the Markdown Guide extended syntax. */
	private function definition_list( $node ) {
		$output = '';
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			$tag  = strtolower( $child->nodeName );
			$text = trim( $this->walk( $child->childNodes ) );
			if ( 'dt' === $tag && '' !== $text ) {
				$output .= $text . "\n";
			} elseif ( 'dd' === $tag && '' !== $text ) {
				$output .= ': ' . $text . "\n";
			}
		}

		return '' === $output ? '' : "\n\n" . rtrim( $output ) . "\n\n";
	}

	/** Convert a preformatted block, preserving an optional language class. */
	private function fenced_code( $node ) {
		$language = '';
		foreach ( $node->getElementsByTagName( 'code' ) as $code ) {
			if ( preg_match( '/(?:language-|lang-)([A-Za-z0-9_+-]+)/', $code->getAttribute( 'class' ), $matches ) ) {
				$language = $matches[1];
			}
			break;
		}
		$code      = trim( $node->textContent );
		$delimiter = '```';
		while ( strpos( $code, $delimiter ) !== false ) {
			$delimiter .= '`';
		}

		return "\n\n" . $delimiter . $language . "\n" . $code . "\n" . $delimiter . "\n\n";
	}

	/** Render inline code with a delimiter that does not occur in the value. */
	private function inline_code( $value ) {
		$value     = trim( $value );
		$delimiter = '`';
		while ( strpos( $value, $delimiter ) !== false ) {
			$delimiter .= '`';
		}

		return '' === $value ? '' : $delimiter . $value . $delimiter;
	}

	/** Escape an optional Markdown link or image title. */
	private function markdown_title( $title ) {
		$title = trim( $title );
		return '' === $title ? '' : ' "' . str_replace( '"', '\\"', $title ) . '"';
	}
}
