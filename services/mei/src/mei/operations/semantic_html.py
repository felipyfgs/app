from dataclasses import dataclass
from html.parser import HTMLParser


@dataclass(frozen=True, slots=True)
class SemanticRow:
    attributes: dict[str, str]
    cells: list[str]
    links: list[str]


class SemanticTableParser(HTMLParser):
    def __init__(self, aria_label: str) -> None:
        super().__init__(convert_charrefs=True)
        self._aria_label = aria_label.casefold()
        self._table_depth = 0
        self._row_attributes: dict[str, str] | None = None
        self._cells: list[str] = []
        self._cell_parts: list[str] | None = None
        self._links: list[str] = []
        self.rows: list[SemanticRow] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attributes = {key: value or "" for key, value in attrs}
        if tag == "table" and self._table_depth == 0:
            if attributes.get("aria-label", "").casefold() == self._aria_label:
                self._table_depth = 1
            return
        if self._table_depth == 0:
            return
        if tag == "table":
            self._table_depth += 1
        elif tag == "tr":
            self._row_attributes = attributes
            self._cells = []
            self._links = []
        elif tag in {"td", "th"} and self._row_attributes is not None:
            self._cell_parts = []
        elif tag == "a" and self._row_attributes is not None:
            href = attributes.get("href", "").strip()
            if href:
                self._links.append(href)

    def handle_endtag(self, tag: str) -> None:
        if self._table_depth == 0:
            return
        if tag in {"td", "th"} and self._cell_parts is not None:
            self._cells.append(" ".join("".join(self._cell_parts).split()))
            self._cell_parts = None
        elif tag == "tr" and self._row_attributes is not None:
            if self._cells:
                self.rows.append(
                    SemanticRow(self._row_attributes, self._cells.copy(), self._links.copy())
                )
            self._row_attributes = None
            self._cells = []
            self._links = []
        elif tag == "table":
            self._table_depth -= 1

    def handle_data(self, data: str) -> None:
        if self._cell_parts is not None:
            self._cell_parts.append(data)


class DocumentMetadataParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.body_attributes: dict[str, str] = {}

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if tag == "body" and not self.body_attributes:
            self.body_attributes = {key: value or "" for key, value in attrs}


def semantic_rows(html: str, aria_label: str) -> list[SemanticRow]:
    parser = SemanticTableParser(aria_label)
    parser.feed(html)
    return parser.rows


def body_attribute(html: str, name: str, default: str = "unknown") -> str:
    parser = DocumentMetadataParser()
    parser.feed(html)
    return parser.body_attributes.get(name, default)
