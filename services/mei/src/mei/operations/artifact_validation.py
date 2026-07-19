# mypy: disable-error-code="no-untyped-call"

import hashlib
from dataclasses import dataclass
from pathlib import Path
from uuid import UUID

import pymupdf
import zxingcpp  # type: ignore[import-not-found]
from PIL import Image

from mei.artifacts import ArtifactStore
from mei.models import ArtifactDescriptor


class InvalidArtifactError(ValueError):
    pass


def validate_pdf(content: bytes, maximum_bytes: int) -> bytes:
    if not content.startswith(b"%PDF-"):
        raise InvalidArtifactError("Conteudo PDF sem assinatura valida")
    if b"%%EOF" not in content[-1_024:]:
        raise InvalidArtifactError("Conteudo PDF incompleto")
    if not 0 < len(content) <= maximum_bytes:
        raise InvalidArtifactError("Conteudo PDF excede o limite")
    return content


def normalize_barcode(value: str) -> str:
    normalized = "".join(character for character in value if character.isdigit())
    if len(normalized) not in {44, 47, 48}:
        raise InvalidArtifactError("Codigo de barras DAS invalido")
    return normalized


def barcode_from_pdf(content: bytes, maximum_bytes: int, maximum_pages: int = 2) -> str:
    validated = validate_pdf(content, maximum_bytes)
    try:
        document = pymupdf.open(stream=validated, filetype="pdf")
    except Exception as error:  # noqa: BLE001 - biblioteca pode variar a excecao por PDF
        raise InvalidArtifactError("PDF DAS nao pode ser renderizado") from error

    with document:
        for page_number in range(min(document.page_count, maximum_pages)):
            page = document.load_page(page_number)
            matrix = pymupdf.Matrix(2.0, 2.0)
            width = int(page.rect.width * matrix.a)
            height = int(page.rect.height * matrix.d)
            if width <= 0 or height <= 0 or width * height > 20_000_000:
                raise InvalidArtifactError("Pagina PDF DAS fora do limite de renderizacao")
            pixmap = page.get_pixmap(matrix=matrix, alpha=False)
            mode = "RGB" if pixmap.n == 3 else "RGBA"
            image = Image.frombytes(mode, (pixmap.width, pixmap.height), pixmap.samples)
            results = zxingcpp.read_barcodes(
                image,
                formats=zxingcpp.BarcodeFormat.LinearCodes,
                try_rotate=True,
                try_downscale=True,
            )
            for result in results:
                try:
                    return normalize_barcode(result.text)
                except InvalidArtifactError:
                    continue

    raise InvalidArtifactError("Codigo de barras nao encontrado no PDF DAS")


@dataclass(frozen=True, slots=True)
class ArtifactPublisher:
    store: ArtifactStore
    maximum_bytes: int

    def pdf(self, job_id: UUID, name: str, content: bytes) -> ArtifactDescriptor:
        validated = validate_pdf(content, self.maximum_bytes)
        return self._write(job_id, name, "application/pdf", validated)

    def text(self, job_id: UUID, name: str, content: str) -> ArtifactDescriptor:
        encoded = content.encode("ascii")
        if not 0 < len(encoded) <= self.maximum_bytes:
            raise InvalidArtifactError("Artefato de texto excede o limite")
        return self._write(job_id, name, "text/plain", encoded)

    def _write(
        self, job_id: UUID, name: str, content_type: str, content: bytes
    ) -> ArtifactDescriptor:
        descriptor = ArtifactDescriptor(
            name=Path(name).name,
            content_type=content_type,
            byte_size=len(content),
            sha256=hashlib.sha256(content).hexdigest(),
        )
        self.store.write(job_id, descriptor.id, content)
        return descriptor
