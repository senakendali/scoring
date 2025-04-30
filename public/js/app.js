function getRoundLabels(totalRounds) {
    const labels = {};

    for (let i = 1; i <= totalRounds; i++) {
        if (totalRounds === 1) {
            labels[i] = "Final";
        } else if (totalRounds === 2) {
            labels[i] = i === 1 ? "Semifinal" : "Final";
        } else if (totalRounds === 3) {
            labels[i] = i === 1 ? "Perempat Final" : (i === 2 ? "Semifinal" : "Final");
        } else {
            if (i === 1) {
                labels[i] = "Penyisihan";
            } else if (i === totalRounds - 2) {
                labels[i] = "Perempat Final";
            } else if (i === totalRounds - 1) {
                labels[i] = "Semifinal";
            } else if (i === totalRounds) {
                labels[i] = "Final";
            } else {
                labels[i] = `Babak ${i}`;
            }
        }
    }

    return labels;
}
