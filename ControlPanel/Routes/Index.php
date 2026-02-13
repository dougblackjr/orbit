<?php

namespace TripleNERDscore\Orbit\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class Index extends AbstractRoute
{
    protected $cp_page_title = 'Orbit';

    public function process($id = false)
    {
        // Get all channels keyed by ID
        $channels = ee('Model')->get('Channel')->all();
        $channelMap = [];
        foreach ($channels as $channel) {
            $channelMap[$channel->channel_id] = $channel->channel_title;
        }

        // Get all relationship fields
        $relFields = ee('Model')->get('ChannelField')
            ->filter('field_type', 'relationship')
            ->all();

        // Build a map of field_id => array of source channel IDs
        // Path 1: Direct field assignments via exp_channels_channel_fields
        $directRows = ee()->db->select('channel_id, field_id')
            ->from('channels_channel_fields')
            ->get()
            ->result_array();

        $directMap = [];
        foreach ($directRows as $row) {
            $directMap[$row['field_id']][] = (int) $row['channel_id'];
        }

        // Path 2: Field group assignments
        // exp_channel_field_groups_fields => group_id -> field_id
        // exp_channels_channel_field_groups => channel_id -> group_id
        $groupFieldRows = ee()->db->select('group_id, field_id')
            ->from('channel_field_groups_fields')
            ->get()
            ->result_array();

        $groupToFields = [];
        foreach ($groupFieldRows as $row) {
            $groupToFields[$row['group_id']][] = (int) $row['field_id'];
        }

        $channelGroupRows = ee()->db->select('channel_id, group_id')
            ->from('channels_channel_field_groups')
            ->get()
            ->result_array();

        $groupMap = [];
        foreach ($channelGroupRows as $row) {
            if (isset($groupToFields[$row['group_id']])) {
                foreach ($groupToFields[$row['group_id']] as $fieldId) {
                    $groupMap[$fieldId][] = (int) $row['channel_id'];
                }
            }
        }

        // Merge both paths: field_id => unique source channel IDs
        $fieldSourceChannels = [];
        foreach ($relFields as $field) {
            $fid = $field->field_id;
            $sources = array_merge(
                $directMap[$fid] ?? [],
                $groupMap[$fid] ?? []
            );
            $fieldSourceChannels[$fid] = array_unique($sources);
        }

        // Build nodes and edges
        $nodes = [];
        $edges = [];
        $usedChannelIds = [];

        foreach ($relFields as $field) {
            $fid = $field->field_id;
            $settings = $field->field_settings;
            $targetChannelIds = $settings['channels'] ?? [];

            // Empty target array means all channels
            if (empty($targetChannelIds)) {
                $targetChannelIds = array_keys($channelMap);
            }

            $sourceChannelIds = $fieldSourceChannels[$fid] ?? [];

            foreach ($sourceChannelIds as $sourceId) {
                if (!isset($channelMap[$sourceId])) {
                    continue;
                }

                foreach ($targetChannelIds as $targetId) {
                    $targetId = (int) $targetId;
                    if (!isset($channelMap[$targetId])) {
                        continue;
                    }

                    $usedChannelIds[$sourceId] = true;
                    $usedChannelIds[$targetId] = true;

                    $edges[] = [
                        'source' => $sourceId,
                        'target' => $targetId,
                        'field' => $field->field_label,
                    ];
                }
            }
        }

        // Only include channels that participate in relationships
        foreach ($usedChannelIds as $cid => $_) {
            $nodes[] = [
                'id' => $cid,
                'name' => $channelMap[$cid],
            ];
        }

        $graphData = json_encode([
            'nodes' => $nodes,
            'edges' => $edges,
        ]);

        $this->setBody('Index', [
            'graphData' => $graphData,
            'hasRelationships' => count($edges) > 0,
        ]);

        return $this;
    }
}
