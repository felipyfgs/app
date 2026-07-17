import { describe, expect, it } from 'vitest'
import {
  documentKindFilterItems,
  documentKindLabel,
  documentKindLabelFromModel,
  isDocumentKindCaptureAvailable
} from '../../app/utils/document-kinds'

describe('documentKinds', () => {
  it('rotula CT-e e modelos SEFAZ do lote misto', () => {
    expect(documentKindLabel('CTE')).toBe('CT-e')
    expect(documentKindLabelFromModel('57')).toBe('CT-e')
    expect(documentKindLabelFromModel('55')).toBe('NF-e')
    expect(documentKindLabelFromModel('65')).toBe('NFC-e')
    expect(documentKindLabelFromModel('67')).toMatch(/CT-e OS/i)
    expect(documentKindLabelFromModel(null)).toBeNull()
  })

  it('lista CT-e no filtro sem “em breve” genérico de captura', () => {
    const items = documentKindFilterItems()
    const cte = items.find(i => i.value === 'CTE')
    expect(cte?.label).toBe('CT-e')
    expect(isDocumentKindCaptureAvailable('CTE')).toBe(true)
  })
})
