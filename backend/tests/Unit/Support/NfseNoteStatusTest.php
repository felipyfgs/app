<?php

namespace Tests\Unit\Support;

use App\Support\NfseNoteStatus;
use PHPUnit\Framework\TestCase;

class NfseNoteStatusTest extends TestCase
{
    public function test_from_c_stat(): void
    {
        $this->assertSame(NfseNoteStatus::ACTIVE, NfseNoteStatus::fromCStat('100'));
        $this->assertSame(NfseNoteStatus::SUBSTITUTE, NfseNoteStatus::fromCStat('101'));
        $this->assertSame(NfseNoteStatus::JUDICIAL, NfseNoteStatus::fromCStat('102'));
        $this->assertSame(NfseNoteStatus::ACTIVE, NfseNoteStatus::fromCStat('103'));
        $this->assertSame(NfseNoteStatus::UNKNOWN, NfseNoteStatus::fromCStat(null));
        $this->assertSame(NfseNoteStatus::UNKNOWN, NfseNoteStatus::fromCStat('999'));
    }

    public function test_from_event_type(): void
    {
        $this->assertSame(NfseNoteStatus::CANCELLED, NfseNoteStatus::fromEventType('CANCELAMENTO'));
        $this->assertSame(NfseNoteStatus::CANCELLED, NfseNoteStatus::fromEventType('e101101'));
        $this->assertSame(NfseNoteStatus::SUPERSEDED, NfseNoteStatus::fromEventType('CANCELAMENTO_POR_SUBSTITUICAO'));
        $this->assertSame(NfseNoteStatus::SUPERSEDED, NfseNoteStatus::fromEventType('e105102'));
        $this->assertNull(NfseNoteStatus::fromEventType('SOLICITACAO_ANALISE_FISCAL'));
        $this->assertNull(NfseNoteStatus::fromEventType(null));
    }

    public function test_operational_groups(): void
    {
        $this->assertSame(NfseNoteStatus::GROUP_AUTHORIZED, NfseNoteStatus::operationalGroup('ACTIVE'));
        $this->assertSame(NfseNoteStatus::GROUP_AUTHORIZED, NfseNoteStatus::operationalGroup('SUBSTITUTE'));
        $this->assertSame(NfseNoteStatus::GROUP_AUTHORIZED, NfseNoteStatus::operationalGroup('JUDICIAL'));
        $this->assertSame(NfseNoteStatus::GROUP_CANCELLED, NfseNoteStatus::operationalGroup('CANCELLED'));
        $this->assertSame(NfseNoteStatus::GROUP_CANCELLED, NfseNoteStatus::operationalGroup('SUPERSEDED'));
        $this->assertSame(NfseNoteStatus::GROUP_REVIEW, NfseNoteStatus::operationalGroup('UNKNOWN'));
    }

    public function test_operational_labels(): void
    {
        $this->assertSame('Autorizada', NfseNoteStatus::label('ACTIVE'));
        $this->assertSame('Autorizada', NfseNoteStatus::label('SUBSTITUTE'));
        $this->assertSame('Autorizada', NfseNoteStatus::label('JUDICIAL'));
        $this->assertSame('Cancelada', NfseNoteStatus::label('CANCELLED'));
        $this->assertSame('Cancelada', NfseNoteStatus::label('SUPERSEDED'));
        $this->assertSame('Em revisão', NfseNoteStatus::label('UNKNOWN'));
    }

    public function test_granular_and_official_description(): void
    {
        $this->assertSame('NFS-e de Substituição Gerada', NfseNoteStatus::cStatDescription('101'));
        $this->assertSame('NFS-e de Substituição', NfseNoteStatus::granularLabel('SUBSTITUTE'));
        $this->assertSame('Substituída', NfseNoteStatus::granularLabel('SUPERSEDED'));
        $this->assertSame(
            'NFS-e Gerada',
            NfseNoteStatus::officialDescription('ACTIVE', '100')
        );
        $this->assertSame(
            'Substituída',
            NfseNoteStatus::officialDescription('SUPERSEDED', null)
        );
    }

    public function test_statuses_for_filter_groups(): void
    {
        $this->assertSame(
            [NfseNoteStatus::ACTIVE, NfseNoteStatus::SUBSTITUTE, NfseNoteStatus::JUDICIAL],
            NfseNoteStatus::statusesForFilter('AUTHORIZED')
        );
        $this->assertSame(
            [NfseNoteStatus::CANCELLED, NfseNoteStatus::SUPERSEDED, NfseNoteStatus::REPLACED],
            NfseNoteStatus::statusesForFilter('CANCELLED')
        );
        $this->assertSame(
            [NfseNoteStatus::UNKNOWN],
            NfseNoteStatus::statusesForFilter('REVIEW')
        );
        $this->assertSame(
            [NfseNoteStatus::UNKNOWN],
            NfseNoteStatus::statusesForFilter('UNKNOWN')
        );
        // Enum granular exato
        $this->assertSame(
            [NfseNoteStatus::SUBSTITUTE],
            NfseNoteStatus::statusesForFilter('SUBSTITUTE')
        );
        $this->assertSame(
            [NfseNoteStatus::SUPERSEDED],
            NfseNoteStatus::statusesForFilter('SUPERSEDED')
        );
    }
}
