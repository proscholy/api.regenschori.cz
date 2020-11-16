import gql from 'graphql-tag';
// import { belongsToManyMutator, belongsToMutator } from './relations';

const fragment = gql`
    fragment TagFillableFragment on Tag {
        id
        name
        description
    }
`;

const QUERY = gql`
    query($id: ID!) {
        model_database: tag(id: $id) {
            ...TagFillableFragment
            type_enum
        }
    }
    ${fragment}
`;

const MUTATION = gql`
    mutation(
        $input: UpdateTagInput!
    ) {
        update_tag(input: $input) {
            ...TagFillableFragment
        }
    }
    ${fragment}
`;

export default {
    fragment,
    QUERY,
    MUTATION,

    getQueryVariables: vueModel => ({
        id: vueModel.id
    }),

    getMutationVariables: vueModel => ({
        input: {
            ...vueModel
        }
    })
};
